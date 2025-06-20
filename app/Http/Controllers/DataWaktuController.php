<?php

namespace App\Http\Controllers;

use Illuminate\Auth\Events\Validated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DataWaktuController extends Controller
{
    public function index()
    {
        $dataWaktu = DB::connection('sekolah')->table('data_waktu')->get();

        if (is_null($dataWaktu)) {
            return response()->json([
                'success' => false,
                'message' => 'data belum tersedia'
            ], 404);
        }

        return response()->json([
            'data' => $dataWaktu
        ], 200);
    }

    public function show($dataWaktuId)
    {
        $dataWaktu = DB::connection('sekolah')->table('data_waktu')->where('data_waktu_id', $dataWaktuId)->first();

        if (is_null($dataWaktu)) {
            return response()->json([
                'success' => false,
                'message' => 'data belum ditemukan'
            ], 404);
        }

        return response()->json([
            'data' => $dataWaktu
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'data_waktu_majors_id' => 'required',
            'data_waktu_day_id' => 'required',
            'data_waktu_masuk' => 'required|date_format:H:i:s',
            'data_waktu_pulang' => 'required|date_format:H:i:s'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'error',
                'error' => $validator->errors()
            ], 404);
        }

        $dataWaktu = DB::connection('sekolah')->table('data_waktu')->insertGetId([
            'data_waktu_majors_id' => $request->data_waktu_majors_id,
            'data_waktu_day_id' => $request->data_waktu_day_id,
            'data_waktu_masuk' => $request->data_waktu_masuk,
            'data_waktu_pulang' => $request->data_waktu_pulang
        ]);

        $dataWaktu = DB::connection('sekolah')->table('data_waktu')->where('data_waktu_id', $dataWaktu)->first();
        return response()->json([
            'message' => 'success',
            'data' => $dataWaktu
        ]);
    }

    public function destroy($dataWaktuId)
    {

        $dataWaktu = DB::connection('sekolah')->table('data_waktu')->where('data_waktu_id', $dataWaktuId)->first();

        if ($dataWaktu) {
            DB::connection('sekolah')->table('data_waktu')->where('data_waktu_id', $dataWaktuId)->delete();
            return response()->json([
                'message' => 'data berhasil dihapus',
                'data' => $dataWaktu
            ], 200);
        } else {
            return response()->json([
                'message' => 'data gagal dihapus'
            ], 404);
        }
    }

    public function update(Request $request, $dataWaktuId)
    {

        $dataWaktu = DB::connection('sekolah')->table('data_waktu')->where('data_waktu_id', $dataWaktuId)->first();
        if (!$dataWaktu) {
            return response()->json([
                'message' => 'data tidak ditemukan'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'data_waktu_majors_id' => 'required',
            'data_waktu_day_id' => 'required',
            'data_waktu_masuk' => 'required|date_format:H:i:s',
            'data_waktu_pulang' => 'required|date_format:H:i:s'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'error',
                'error' => $validator->errors()
            ], 404);
        }

        $data_update = [];
        if ($request->filled('data_waktu_majors_id')) {
            $data_update['data_waktu_majors_id'] = $request->data_waktu_majors_id;
        }

        if ($request->filled('data_waktu_day_id')) {
            $data_update['data_waktu_day_id'] = $request->data_waktu_day_id;
        }

        if ($request->filled('data_waktu_masuk')) {
            $data_update['data_waktu_masuk'] = $request->data_waktu_masuk;
        }

        if ($request->filled('data_waktu_pulang')) {
            $data_update['data_waktu_pulang'] = $request->data_waktu_pulang;
        }

        DB::connection('sekolah')->table('data_waktu')->where('data_waktu_id', $dataWaktuId)->update($data_update);
        return response()->json([
            "message" => "data berhasil diUpdate",
            "data" => DB::connection('sekolah')->table('data_waktu')->where('data_waktu_id', $dataWaktuId)->first()
        ], 200);
    }
}
