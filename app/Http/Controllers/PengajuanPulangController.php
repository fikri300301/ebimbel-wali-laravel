<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Period;
use Illuminate\Http\Request;
use App\Models\PengajuanPulang;
use App\Models\Pulang;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Arr;

class PengajuanPulangController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $data = PengajuanPulang::where('pengajuan_pulang_student_id', $user->student_id)->get()->map(function ($item) {
            $status = $item->pengajuan_pulang_status;
            return [
                'pengajuan_pulang_note' => $item->pengajuan_pulang_note,
                'pengajuan_pulang_date' => $item->pengajuan_pulang_date,
                'status_izin' => $status == "Diajukan" ? 2 : ($status == "Disetujui" ? 1 : ($status == "Ditolak" ? 0 : 3))
            ];
        });
        $data2 = Pulang::where('pulang_student_id', $user->student_id)->get()->map(function ($item) {
            return [
                'pengajuan_pulang_note' => $item->pulang_note,
                'pengajuan_pulang_date' => $item->pulang_date,
                'status_izin' => $item->pulang_status
            ];
        });
        // $data = $data = PengajuanPulang::where('pengajuan_pulang_student_id', $user->student_id)->get();
        if ($data) {
            return response()->json([
                'is_correct' => true,
                'message' => 'success',
                'data' => array_values(Arr::sortDesc([...$data, ...$data2], function ($value) {
                    return $value['pengajuan_pulang_date'];
                }))
            ], 200);
        }
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        $period = Period::where('period_status', 1)->first();
        $validator = Validator::make($request->all(), [
            'pengajuan_pulang_date' => 'required',
            'pengajuan_pulang_days' => 'nullable',
            'pengajuan_pulang_period_id' => 'nullable',
            'pengajuan_pulang_majors_id' => 'nullable',
            'pengajuan_pulang_student_id' => 'nullable',
            'pengajuan_pulang_note' => 'required',
            'pengajuan_pulang_status' => 'nullable',
            'pengajuan_pulang_user_id' => 'nullable',
            'pengajuan_pulang_created_at' => 'nullable',
            'pengajuan_pulang_updated_at' => 'nullable'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'is_correct' => false,
                'message' => $validator->error()
            ], 400);
        }
        $waktu = Carbon::now('Asia/Jakarta');
        $waktu->locale('id');
        $data = PengajuanPulang::create([
            'pengajuan_pulang_date' => $request->pengajuan_pulang_date,
            'pengajuan_pulang_days' => 3,
            'pengajuan_pulang_period_id' => $period->period_id,
            'pengajuan_pulang_majors_id' => $user->majors_majors_id,
            'pengajuan_pulang_student_id' => $user->student_id,
            'pengajuan_pulang_note' => $request->pengajuan_pulang_note,
            'pengajuan_pulang_status' => 'Diajukan',
            'pengajuan_pulang_user_id' => null,
            'pengajuan_pulang_created_at' => $waktu,
            'pengajuan_pulang_updated_at' => $waktu
        ]);

        return response()->json([
            'is_correct' => true,
            'message' => 'success'
        ], 200);
    }
}
