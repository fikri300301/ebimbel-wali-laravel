<?php

namespace App\Http\Controllers;

use App\Models\Family;
use Illuminate\Http\Request;
use App\Services\DatabaseSwitcher;
use Illuminate\Support\Facades\Validator;

class FamilyController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/auth/employee-fam",
     *     summary="Get data employee-fam",
     *     description="Mengambil semua data fam berdasarkan user login",
     *     tags={"Profil_Riwayat_hidup-Family"},
     *     security={{"BearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Data Family berhasil diambil",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="fam_id", type="string", example="1"),
     *                     @OA\Property(property="fam_name", type="string", example="joko"),
     *                     @OA\Property(property="fam_desc", type="string", example="1"),
     *                     @OA\Property(property="fam_employee_id", type="integer", example="118"),
     *                     @OA\Property(property="sekolah_id", type="integer", example="1"),
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="null",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="error")
     *         )
     *     )
     * )
     */

    // protected $databaseSwitcher;
    // public function __construct(DatabaseSwitcher $databaseSwitcher)
    // {
    //     $this->databaseSwitcher = $databaseSwitcher;
    // }
    public function index()
    {
        //$this->databaseSwitcher->switchDatabaseFromToken(new Family());
        $user = auth()->user();
        $data = Family::where('fam_employee_id', $user->employee_id)->get();

        if ($data->isEmpty()) {
            return response()->json([
                'is_correct' => false,
                'message' => 'data belum tersedia'
            ], 200);
        }

        return response()->json([
            'is_correct' => true,
            'message' => 'success',
            'data' => $data
        ], 404);
    }

    /**
     * @OA\Post(
     *     path="/api/auth/employee-fam",
     *     summary="Membuat data employee-fam",
     *     description="Mengirim data employee-fam",
     *     tags={"Profil_Riwayat_hidup-Family"},
     *     security={{"BearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         content={
     *             @OA\MediaType(
     *                 mediaType="multipart/form-data",
     *                 @OA\Schema(
     *                     required={"fam_name","fam_desc",},
     *                     @OA\Property(property="fam_name", type="string", example="fikri"),
     *                     @OA\Property(property="fam_desc", type="string", example="1"),

     *                 )
     *             )
     *         }
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Data family berhasil disimpan",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="success")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validasi data family gagal",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=false),
     *             @OA\Property(property="error", type="object", additionalProperties={"type": "string"})
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        // $this->databaseSwitcher->switchDatabaseFromToken(new Family());
        $validator = Validator::make($request->all(), [
            'fam_name' => 'required',
            'fam_desc' => 'required',
            'fam_employee_id' => 'nullable',
            'sekolah_id' => 'nullable'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'is_correct' => false,
                'message' => $validator->errors()
            ], 404);
        }
        $user = auth()->user();
        $employee = $user->employee_id;
        $sekolah = $user->sekolah_id;

        $data = Family::create([
            'fam_name' => $request->fam_name,
            'fam_desc' => $request->fam_desc,
            'fam_employee_id' => $employee,
            'sekolah_id' => $sekolah,
        ]);

        return response()->json([
            'is_correct' => true,
            'message' => 'success'
        ], 200);
    }

    /**
     * @OA\Delete(
     *     path="/api/auth/employee-fam/{fam_id}",
     *     summary="Delete family",
     *     description="Menghapus data family berdasarkan ID",
     *     tags={"Profil_Riwayat_hidup-Family"},
     *     security={{"BearerAuth": {}}},
     *     @OA\Parameter(
     *         name="fam_id",
     *         in="path",
     *         required=true,
     *         description="ID family untuk menghapus data ",
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Data berhasil dihapus",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="data berhasil dihapus")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Data tidak ditemukan",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="data tidak ditemukan")
     *         )
     *     )
     * )
     */
    public function destroy($fam_id)
    {
        //$this->databaseSwitcher->switchDatabaseFromToken(new Family());
        $user = auth()->user();
        $data = Family::where('fam_id', $fam_id)->where('fam_employee_id', $user->employee_id)->first();

        if ($data) {
            $data->delete();
            return response()->json([
                'is_correct' => true,
                'message' => 'success'
            ], 200);
        } else {
            return response()->json([
                'is_correct' => false,
                'message' => 'data tidak ditemukan'
            ], 404);
        }
    }

    /**
     * @OA\Patch(
     *     path="/api/auth/employee-fam/{fam_id}",
     *     summary="update data employee-family",
     *     description="Upate data employee-education",
     *     tags={"Profil_Riwayat_hidup-Family"},
     *     security={{"BearerAuth": {}}},
     *      *     @OA\Parameter(
     *         name="fam_id",
     *         in="path",
     *         required=true,
     *         description="ID fam untuk memperbarui data",
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         content={
     *             @OA\MediaType(
     *                 mediaType="application/json",
     *                 @OA\Schema(
     *                     required={"education_start","education_end","education_name","education_location" },
     *                     @OA\Property(property="fam_name", type="string", example="joko"),
     *                     @OA\Property(property="fam_desc", type="string", example="1"),
     *                 )
     *             )
     *         }
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Data family berhasil diupate",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="success")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validasi data family gagal",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=false),
     *             @OA\Property(property="error", type="object", additionalProperties={"type": "string"})
     *         )
     *     )
     * )
     */
    public function update(Request $request, $fam_id)
    {
        //$this->databaseSwitcher->switchDatabaseFromToken(new Family());
        $user = auth()->user();
        $data = Family::where('fam_id', $fam_id)->where('fam_employee_id', $user->employee_id)->first();
        if ($data) {
            $validator = Validator::make($request->all(), [
                'fam_name' => 'required',
                'fam_desc' => 'required',
                'fam_employee_id' => 'nullable',
                'sekolah_id' => 'nullable'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'is_correct' => false,
                    'message' => $validator->errors()
                ], 200);
            }

            $user = auth()->user();
            $employee = $user->employee_id;
            $sekolah = $user->sekolah_id;

            $data->fam_name = $request->input('fam_name');
            $data->fam_desc = $request->input('fam_desc');
            $data->fam_employee_id = $employee;
            $data->sekolah_id = $sekolah;

            $data->save();

            return response()->json([
                'is_correct' => true,
                'message' => 'success',
            ], 200);
        } else {
            return response()->json([
                'is_correct' => false,
                'message' => 'not found'
            ], 404);
        }
    }
}
