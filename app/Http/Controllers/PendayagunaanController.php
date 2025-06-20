<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\Pendayagunaan;

class PendayagunaanController extends Controller
{
    public function index(Request $request)
    {
        $data = Pendayagunaan::where('program_program_id', $request->program_id)
            ->orderBy('created_at', 'desc')
            ->get(); //perbaikan
        if ($data->isEmpty()) {
            Pendayagunaan::create([
                'program_program_id' => $request->program_id,
                'pendayagunaan_title' => 'Program Donasi Dibuat',
                'pendayagunaan_nominal' => 0,
                'pendayagunaan_note' => ' ',
                'pendayagunaan_date' => Carbon::now()->format('Y-m-d'),
                'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'updated_at' => Carbon::now()->format('Y-m-d H:i:s')

            ]);
        }
        return response()->json([
            'is_correct' => true,
            'pendayagunaan' => $data
        ]);
    }
}
