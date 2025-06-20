<?php

namespace App\Http\Controllers;

use App\Models\DataAbsen;
use App\Models\Information;
use App\Models\major;
use Illuminate\Http\Request;
use App\Services\DatabaseSwitcher;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Validator;

class MajorController extends Controller
{

    public function index(Request $request)
    {
        $data = major::all();
        if ($data->isEmpty()) {
            return response()->json([
                'is_correct' => false,
                'message' => 'Anda tidak terdaftar.',
            ], 404);
        }

        return response()->json([
            'is_correct' => true,
            'message' => 'success',
            'unit' => $data->map(function ($item) {
                return [
                    'id_unit' => $item->majors_id,
                    'nama_unit' => $item->majors_name,
                ];
            }),
        ], 200);
    }
    /**
     * @OA\Get(
     *     path="/api/majors",
     *     summary="Get data majors",
     *     tags={"Majors"},
     *     security={{"BearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Berhasil mendapatkan data",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean"),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(
     *                 property="unit",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id_unit", type="integer"),
     *                     @OA\Property(property="nama_unit", type="string")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Token tidak valid."
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Data tidak ditemukan."
     *     )
     * )
     */



    public function show($majors_id)
    {
        $majors = DB::connection('sekolah')->table('majors')->where('majors_id', $majors_id)->first();

        if (is_null($majors)) {
            return response()->json([
                'success' => false,
                'message' => 'data tidak ditemukan'
            ], 404);
        }

        return response()->json([
            "data" => $majors
        ], 200);
    }

    // public function store(Request $request)
    // {
    //     $validator = Validator::make($request->all(), [
    //         'majors_name' => 'required|string',
    //         'majors_short_name' => 'required|string',
    //         'majors_school_name' => 'required|string',
    //         'majors_status' => 'required',
    //         'sekolah_id' => 'required'
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'message' => 'error',
    //             'error' => $validator->errors()
    //         ], 404);
    //     }

    //     $majors = DB::connection('sekolah')->table('majors')->insertGetId([
    //         'majors_name' => $request->majors_name,
    //         'majors_short_name' => $request->majors_short_name,
    //         'majors_school_name' => $request->majors_school_name,
    //         'majors_status' => $request->majors_status,
    //         'sekolah_id' => $request->sekolah_id

    //     ]);

    //     $majors = DB::connection('sekolah')->table('majors')->where('majors_id', $majors)->first();
    //     return response()->json([
    //         'message' => 'data berhasil dibuat',
    //         'data' => $majors
    //     ], 200);
    // }

    public function destroy($majors_id)
    {

        $majors = DB::connection('sekolah')->table('majors')->where('majors_id', $majors_id)->first();

        if ($majors) {

            DB::connection('sekolah')->table('majors')->where('majors_id', $majors_id)->delete();
            return response()->json([
                'message' => 'data berhasil dihapus',
                'data' => $majors
            ], 200);
        } else {
            return response()->json([
                'message' => 'data gagal dihapus'
            ], 404);
        }
    }

    public function update(Request $request, $majors_id)
    {
        $validator = Validator::make($request->all(), [
            'majors_name' => 'required|string',
            'majors_short_name' => 'required|string',
            'majors_school_name' => 'required|string',
            'majors_status' => 'required',
            'sekolah_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'error',
                'error' => $validator->errors()
            ], 404);
        }

        $majors = DB::connection('sekolah')->table('majors')->where('majors_id', $majors_id)->first();
        if (!$majors) {
            return response()->json([
                'message' => 'data tidak ditemukan'
            ], 404);
        }

        // Menyusun data yang akan diupdate
        $data_update = [];
        if ($request->filled('majors_name')) {
            $data_update['majors_name'] = $request->majors_name;
        }

        if ($request->filled('majors_short_name')) {
            $data_update['majors_short_name'] = $request->majors_short_name;
        }

        if ($request->filled('majors_school_name')) {
            $data_update['majors_school_name'] = $request->majors_school_name;
        }

        if ($request->filled('majors_status')) {
            $data_update['majors_status'] = $request->majors_status;
        }

        if ($request->filled('sekolah_id')) {
            $data_update['sekolah_id'] = $request->sekolah_id;
        }

        DB::connection('sekolah')->table('majors')->where('majors_id', $majors_id)->update($data_update);

        return response()->json([
            'message' => 'Data Berhasil diperbarui',
            'data' => DB::connection('sekolah')->table('majors')->where('majors_id', $majors_id)->first()
        ], 200);
    }
}
