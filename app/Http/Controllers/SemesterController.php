<?php

namespace App\Http\Controllers;

use App\Models\Period;
use App\Models\Semester;
use Illuminate\Http\Request;

class SemesterController extends Controller
{
    public function index()
    {
        // $period = Period::all();
        // dd($period);
        $data = Semester::with('period')->get()->map(function ($item) {
            return [
                'semester_id' => $item->semester_id,
                'semester_name' => $item->semester_name,
                'semester_period_id' => $item->semester_period_id,
                'semester_period' => $item->period->period_start . '/' . $item->period->period_end, // Mengambil period_start dari relasi
            ];
        });
        if ($data) {
            return response()->json([
                'is_correct' => true,
                'message' => 'success',
                'semester' => $data
            ], 200);
        } else {
            return response()->json([
                'is_correct' => false,
                'message' => 'data not found'
            ], 400);
        }
    }
}
