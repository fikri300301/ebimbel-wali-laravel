<?php

namespace App\Http\Controllers;

use App\Models\Period;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PeriodController extends Controller
{
    public function index()
    {
        $period = Period::all()->map(function ($item) {
            return [
                'period_id' => $item->period_id,
                'tahun' => $item->period_start . '/' . $item->period_end,
            ];
        });

        if (is_null($period)) {
            return response()->json([
                'success' => false,
                'message' => 'data belum tersedia'
            ], 404);
        }

        return response()->json([
            'data' => $period
        ]);
    }

    public function show($periodId)
    {
        $period = DB::connection('sekolah')->table('period')->where('period_id', $periodId)->first();

        if (is_null($period)) {
            return response()->json([
                'success' => false,
                'message' => 'data belum tersedia'
            ], 404);
        }

        return response()->json([
            'data' => $period
        ], 200);
    }

    public function store(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'period_start' => 'required|integer|digits:4',
            'period_end' => 'nullable|integer|digits:4',
            'period_status' => 'required|in:1,0',
            'sekolah_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'error',
                'error' => $validator->errors()
            ], 404);
        }

        $period = DB::connection('sekolah')->table('period')->insertGetId([
            'period_start' => $request->period_start,
            'period_end' => $request->period_end,
            'period_status' => $request->period_status,
            'sekolah_id' => $request->sekolah_id
        ], 200);

        $period = DB::connection('sekolah')->table('period')->where('period_id', $period)->first();
        return response()->json([
            'message' => 'data berhasil dibuat',
            'data' => $period
        ]);
    }

    public function destroy($periodId)
    {
        $period = DB::connection('sekolah')->table('period')->where('period_id', $periodId)->first();

        if ($period) {
            DB::connection('sekolah')->table('period')->where('period_id', $periodId)->delete();
            return response()->json([
                'message' => 'data berhasil dihapus',
                'data' => $period
            ]);
        } else {
            return response()->json([
                'error' => 'data gagal dihapus'
            ]);
        }
    }

    public function update(Request $request, $periodId)
    {

        $period = DB::connection('sekolah')->table('period')->where('period_id', $periodId)->first();

        if (!$period) {
            return response()->json([
                'message' => 'data tidak ditemukan'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'period_start' => 'required|integer|digits:4',
            'period_end' => 'nullable|integer|digits:4',
            'period_status' => 'required|in:1,0',
            'sekolah_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'error',
                'error' => $validator->errors()
            ], 404);
        }

        $data_update = [];
        if ($request->filled('period_start')) {
            $data_update['period_start'] = $request->period_start;
        }

        $data_update = [];
        if ($request->filled('period_end')) {
            $data_update['period_end'] = $request->period_end;
        }

        $data_update = [];
        if ($request->filled('period_status')) {
            $data_update['period_status'] = $request->period_status;
        }

        $data_update = [];
        if ($request->filled('sekolah_id')) {
            $data_update['sekolah_id'] = $request->sekolah_id;
        }

        DB::connection('sekolah')->table('period')->where('period_id', $periodId)->update($data_update);

        return response()->json([
            'message' => 'success',
            'data' => DB::connection('sekolah')->table('period')->where('period_id', $periodId)->first()
        ]);
    }
}
