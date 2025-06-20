<?php

namespace App\Http\Controllers;

use App\Models\Kesehatan;
use Illuminate\Http\Request;

class KesehatanController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $data = Kesehatan::where('kesehatan_student_id', $user->student_id)->get()->map(function ($item) {
            return [
                'kesehatan_date' => $item->kesehatan_date,
                'kesehatan_ill' => $item->kesehatan_ill,
                'kesehatan_cure' => $item->kesehatan_cure,
                'kesehatan_note' => $item->kesehatan_note
            ];
        });
        if ($data) {
            return response()->json([
                'is_correct' => true,
                'message' => 'success',
                'data' => $data
            ], 200);
        } else {
            return response()->json([
                'is_correct' => false,
                'message' => 'data not found'
            ]);
        }
    }
}
