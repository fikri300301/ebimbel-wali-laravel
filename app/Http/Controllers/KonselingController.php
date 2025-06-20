<?php

namespace App\Http\Controllers;

use App\Models\Konseling;
use Illuminate\Http\Request;

class KonselingController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $periodId = $request->query('period_id');
        $query = Konseling::where('konseling_student_id', $user->student_id);
        if ($periodId) {
            $query->where('konseling_period_id', $periodId);
        }
        $totalPoint = intval($query->where('konseling_student_id', $user->student_id)->sum('konseling_poin'));
        $data = $query->get()->map(function ($item) use ($user) {
            return [
                // 'nama' => $item->student_full_name,
                // 'kelas' => $user->kelas->class_name,
                'pelanggaran' => $item->konseling_foul,
                'poin' => (int)$item->konseling_poin,
                'tanggal_pelanggaran' => $item->konseling_date,
                'tindakan' => $item->konseling_action,
                'catatan' => $item->konseling_note,
                'konseling_period_id' => $item->konseling_period_id
            ];
        });
        if ($data) {
            return response()->json([
                'is_correct' => true,
                'message' => 'success',
                'nama' => $user->student_full_name,
                'kelas' => $user->kelas->class_name,
                'total_point' => $totalPoint,
                'konseling' => $data
            ], 200);
        } else {
            return response()->json([
                'is_correct' => false,
                'message' => 'data not found'
            ], 400);
        }
    }
}
