<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Alternative;
use App\Models\AlternativeCriteria;
use App\Models\Criteria;
use App\Models\NilaiUtility;
use App\Models\NilaiAkhir;

class HomeController extends Controller
{
    public function index()
    {
        $alternatives = Alternative::with('alternativeCriteria')->get();
        $criterias = Criteria::get();

        return view('home', compact('alternatives', 'criterias'));
    }
    
    public function hasilOperasi()
    {
        $arrBobotKriteria = [];
        $criterias = Criteria::get();
        $alternatives = Alternative::get();
        foreach($criterias as $e){
            array_push($arrBobotKriteria, $e->weight/Criteria::sum('weight'));
        }

        // Nilai Utility
        NilaiUtility::where('id', '!=', null)->delete();
        $arrMinMax = [];
        foreach($criterias as $c){
            // var min & max dari c[$i]
            $max = AlternativeCriteria::where('criteria_id', $c->id)->max('score');
            $min = (AlternativeCriteria::where('criteria_id', $c->id)->count() == 1) ? 0 : AlternativeCriteria::where('criteria_id', $c->id)->min('score');
            
            $isBenefit = ($c->type === 'benefit') ? true : false;
            // for sebanyak a
            foreach($alternatives as $a){
                // proses utility dari a[$i] pada c[$i]
                if($isBenefit) {
                    // rumus benefit
                    if(AlternativeCriteria::where('criteria_id', $c->id)->where('alternative_id', $a->id)->count() > 0) {
                        $u = (AlternativeCriteria::where('criteria_id', $c->id)->where('alternative_id', $a->id)->first()->score - $min) / ($max - $min);
                    }else{
                        $u = 0;
                    }
                    NilaiUtility::create([
                        'utility_score' => $u,
                        'alternative_id' => $a->id,
                        'criteria_id' => $c->id,
                    ]);
                }else {
                    // rumus cost 
                    if(AlternativeCriteria::where('criteria_id', $c->id)->where('alternative_id', $a->id)->count() > 0) {
                        $u = ($max - AlternativeCriteria::where('criteria_id', $c->id)->where('alternative_id', $a->id)->first()->score) / ($max - $min);
                    }else{
                        $u = 0;
                    }
                    NilaiUtility::create([
                        'utility_score' => $u,
                        'alternative_id' => $a->id,
                        'criteria_id' => $c->id,
                    ]);
                }
            }
        }
        
        // Nilai Akhir
        NilaiAkhir::where('id', '!=', null)->delete();
        $nilaiAkhir = 0;
        foreach($alternatives as $a) {
            foreach($criterias as $i => $c) { 
                $nilaiAkhir += $arrBobotKriteria[$i] * NilaiUtility::where('alternative_id', $a->id)->where('criteria_id', $c->id)->first()->utility_score;
            }
            NilaiAkhir::create([
                'alternative_id' => $a->id,
                'nilai_akhir' => $nilaiAkhir
            ]);
            $nilaiAkhir = 0;
        }

        $data = NilaiAkhir::with('alternative')->orderBy('nilai_akhir', 'DESC')->get();

        return view('hasil', compact('data'));
    }

    public function showAddAlternative()
    {
        $criterias = Criteria::get();
        return view('add-alternative', compact('criterias'));
    }

    public function addAlternative(Request $req)
    {
        $a = Alternative::create([
            'name' => $req->name
        ]);

        foreach(Criteria::get() as $c){
            AlternativeCriteria::create([
                'score' => $req->{'score'.$c->id},
                'alternative_id' => $a->id,
                'criteria_id' => $c->id
            ]);
        }

        return redirect('/');
    }

    public function showAddCriteria()
    {
        return view('add-criteria');
    }
    public function addCriteria(Request $req)
{
    // Calculate the current total weight of existing criteria
    $currentTotalWeight = Criteria::sum('weight');

    // Check if adding the new weight will exceed 100
    $proposedTotal = $currentTotalWeight + $req->weight;

    if ($proposedTotal > 100) {
        return redirect('/')
            ->with('warning', 'Total bobot kriteria tidak bisa lebih dari 100. Total bobot saat ini: ' . $currentTotalWeight);
    }

    // Check if a criterion with the same name exists and delete it
    $existingCriteria = Criteria::where('name', $req->name)->first();
    if ($existingCriteria) {
        $existingCriteria->delete();
    }

    // Create the new criterion if it doesn't exceed the weight limit
    Criteria::create([
        'name' => $req->name,
        'weight' => $req->weight,
        'type' => $req->type
    ]);

    return redirect('/');
}
  

}