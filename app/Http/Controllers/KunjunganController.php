<?php

namespace App\Http\Controllers;

use App\Models\Guest;
use App\Models\GuestList;
use App\Models\ListTamu;
use Illuminate\Http\Request;

class KunjunganController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $guestData = GuestList::where('guest_list_student_id', $user->student_id)
            ->with(['listTamus.guest'])
            ->get()
            ->flatMap(function ($guestList) {
                return $guestList->listTamus->map(function ($listTamu) use ($guestList) {
                    return [
                        'nama' => $listTamu->guest->guest_name ?? null,
                        'tanggal' => $guestList->guest_list_date,
                        'jam' => $guestList->guest_list_time,
                    ];
                });
            });

        // Mengelompokkan berdasarkan tanggal dan jam
        $groupedData = $guestData->groupBy(function ($item) {
            return $item['tanggal'] . '|' . $item['jam']; // Membuat kunci unik untuk pengelompokan
        })->map(function ($group) {
            return [
                'nama' => $group->pluck('nama')->filter()->values()->all(), // Mengumpulkan nama dan menghapus nilai null
                'tanggal' => $group->first()['tanggal'],
                'jam' => $group->first()['jam'],
            ];
        })->values();

        return response()->json([
            'is_correct' => true,
            'message' => 'sukses',
            'kunjungan' => $groupedData
        ]);
    }
}
