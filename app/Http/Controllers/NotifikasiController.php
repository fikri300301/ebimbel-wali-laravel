<?php

namespace App\Http\Controllers;

use App\Models\InfoApp;
use Illuminate\Http\Request;

class NotifikasiController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        // Ambil data dengan urutan terbaru
        $data = InfoApp::where('student_id', $user->student_id)
            ->orderBy('created_at', 'desc')
            ->get();

        // Mengelompokkan data berdasarkan 'created_at' (tanggal yang sama akan digabungkan)
        $groupedData = $data->groupBy('created_at')->map(function ($items) {
            // Gabungkan informasi yang unik dalam array
            return [
                'created_at' => $items->first()->created_at, // Tanggal pertama dalam grup
                'info' => $items->pluck('info')->unique()->values()->toArray() // Gabungkan informasi menjadi array unik dan urut
            ];
        });

        // Jika Anda hanya ingin mengembalikan tanggal yang unik
        $formattedData = $groupedData->values();

        return response()->json([
            'is_correct' => true,
            'message' => 'success',
            'notifikasi' => $formattedData
        ]);
    }
}
