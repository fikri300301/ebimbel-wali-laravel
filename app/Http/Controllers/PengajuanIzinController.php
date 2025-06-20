<?php

namespace App\Http\Controllers;

use App\Models\Izin;
use Carbon\Carbon;
use App\Models\Period;
use Illuminate\Http\Request;
use App\Models\PengajuanIzin;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Arr;


class PengajuanIzinController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $data = PengajuanIzin::where('pengajuan_izin_student_id', $user->student_id)->get()->map(function ($item) {
            return [
                'pengajuan_izin_note' => $item->pengajuan_izin_note,
                'pengajuan_izin_date' => $item->pengajuan_izin_date,

                'status_izin' => $item->pengajuan_izin_status == 'Diajukan' ? 2 : ($item->pengajuan_izin_status == 'Ditolak' ? 0 : ($item->pengajuan_izin_status == 'Disetujui' ? 1 : 3)),
                'jam' => $item->pengajuan_izin_time,


            ];
        });
        $data2 = Izin::where("izin_student_id", $user->student_id)->get()->map(function ($item) {
            return [
                'pengajuan_izin_note' => $item->izin_note,
                'pengajuan_izin_date' => $item->izin_date,
                'status_izin' => $item->izin_status == 'Diajukan' ? 2 : ($item->izin_status == 'Ditolak' ? 0 : ($item->izin_status == 'Disetujui' ? 1 : 3)),
                'jam' => $item->izin_time,

            ];
        });


        return response()->json([
            'is_correct' => true,
            'message' => 'success',
            'data' => array_values(Arr::sortDesc([...$data, ...$data2], function ($value) {
                return $value['pengajuan_izin_date'];
            }))
        ], 200);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        $period = Period::where('period_status', 1)->first();
        $validator = Validator::make(
            $request->all(),
            [
                'date' => 'required|date',
                'time' => 'required',
                'pengajuan_izin_period_id' => 'nullable',
                'pengajuan_izin_majors_id' => 'nullable',
                'pengajuan_izin_student_id' => 'nullable',
                'note' => 'required',
                'pengajuan_izin_status' => 'nullable',
                'pengajuan_izin_user_id' => 'nullable',
                'pengajuan_izin_created_at' => 'nullable',
                'pengajuan_izin_updated_at' => 'nullable'
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'is_correct' => true,
                'message' => $validator->errors()
            ]);
        }
        $waktu = Carbon::now('Asia/Jakarta');
        $waktu->locale('id');
        $data = PengajuanIzin::create([
            'pengajuan_izin_date' => $request->date,
            'pengajuan_izin_time' => $request->time,
            'pengajuan_izin_period_id' => $period->period_id,
            'pengajuan_izin_majors_id' => $user->majors_majors_id,
            'pengajuan_izin_student_id' => $user->student_id,
            'pengajuan_izin_note' => $request->note,
            'pengajuan_izin_status' => 'Diajukan',
            'pengajuan_user_id' => null,
            'pengajuan_izin_created_at' => $waktu,
            'pengajuan_izin_updated_at' => $waktu
        ]);

        return response()->json([
            'is_correct' => true,
            'message' => 'success'
        ]);
    }
}
