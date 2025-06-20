<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PositionController extends Controller
{
    public function index()
    {

        $position = DB::connection('sekolah')->table('position')->get();

        if (is_null($position)) {
            return response()->json([
                'success' => false,
                'message' => 'data belum tersedia',
            ], 404);
        }

        return response()->json([
            'data' => $position
        ], 200);
    }

    public function show($position_id)
    {

        $postion = DB::connection('sekolah')->table('position')->where('position_id', $position_id)->first();
        if (is_null($postion)) {
            return response()->json([
                'succsess' => false,
                'message' => 'data tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'data' => $postion
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'position_code' => 'required|string',
            'position_name' => 'required|string',
            'position_category' => 'required|in:K,GD,GL',
            'position_majors_id' => 'required',
            'sekolah_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'error',
                'error' => $validator->errors()
            ]);
        }

        $position = DB::connection('sekolah')->table('position')->insertGetId([
            'position_code' => $request->position_code,
            'position_name' => $request->position_name,
            'position_category' => $request->position_category,
            'position_majors_id' => $request->position_majors_id,
            'sekolah_id' => $request->sekolah_id
        ]);

        $position = DB::connection('sekolah')->table('position')->where('position_id', $position)->first();
        return response()->json([
            'message' => 'data berhasil dibuat',
            'data' => $position
        ]);
    }

    public function destroy($position_id)
    {
        $position = DB::connection('sekolah')->table('position')->where('position_id', $position_id)->first();

        if ($position) {
            DB::connection('sekolah')->table('position')->where('position_id', $position_id)->delete();
            return response()->json([
                'message' => 'data berhasil dihapus',
                'data' => $position
            ], 200);
        } else {
            return response()->json([
                'message' => 'data gagal dihapus'
            ]);
        }
    }

    public function update(Request $request, $position_id)
    {
        $validator = Validator::make($request->all(), [
            'position_code' => 'required|string',
            'position_name' => 'required|string',
            'position_category' => 'required|in:K,GD,GL',
            'position_majors_id' => 'required',
            'sekolah_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'error',
                'error' => $validator->errors()
            ]);
        }

        $position = DB::connection('sekolah')->table('position')->where('position_id', $position_id)->first();
        if (!$position) {
            return response()->json([
                'message' => 'data tidak ditemukan'
            ]);
        }

        // Menyusun data yang akan diupdate
        $data_update = [];
        if ($request->filled('position_code')) {
            $data_update['position_code'] = $request->position_code;
        }

        if ($request->filled('position_name')) {
            $data_update['position_name'] = $request->position_name;
        }

        if ($request->filled('position_category')) {
            $data_update['position_category'] = $request->position_category;
        }

        if ($request->filled('position_majors_id')) {
            $data_update['position_majors_id'] = $request->position_majors_id;
        }

        if ($request->filled('sekolah_id')) {
            $data_update['sekolah_id'] = $request->sekolah_id;
        }

        DB::connection('sekolah')->table('position')->where('position_id', $position_id)->update($data_update);

        return response()->json([
            'message' => "data berhasil diupdate",
            'data' => DB::connection('sekolah')->table('position')->where('position_id', $position_id)->first()
        ]);
    }
}
