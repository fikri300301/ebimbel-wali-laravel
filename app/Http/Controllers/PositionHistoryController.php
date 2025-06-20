<?php

namespace App\Http\Controllers;

use App\Models\poshistory;
use Illuminate\Http\Request;
use App\Services\DatabaseSwitcher;
use Illuminate\Support\Facades\Validator;

class PositionHistoryController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/auth/position-history",
     *     summary="Get data position history",
     *     description="Mengambil semua data position history berdasarkan user login",
     *     tags={"Profil_Riwayat_hidup-Position"},
     *     security={{"BearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Data position history berhasil diambil",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="poshistory_id", type="string", example="1"),
     *                     @OA\Property(property="poshistory_start", type="string", example="2000"),
     *                     @OA\Property(property="poshistory_end", type="string", example="2024"),
     *                     @OA\Property(property="poshistory_desc", type="string", example="ilmu pengetahuan alam"),
     *                     @OA\Property(property="poshistory_employee_id", type="integer", example="118"),
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
    protected $databaseSwitcher;
    public function __construct(DatabaseSwitcher $databaseSwitcher)
    {
        $this->databaseSwitcher = $databaseSwitcher;
    }
    public function index()
    {
        $this->databaseSwitcher->switchDatabaseFromToken(new poshistory());
        $user = auth()->user();
        $data = poshistory::where('poshistory_employee_id', $user->employee_id)->get();

        if ($data->isEMpty()) {
            return response()->json([
                'is_correct' => false,
                'message' => 'data belum tersedia'
            ], 200);
        }

        return response()->json([
            'is_correct' => true,
            'message' => 'success',
            'data' => $data
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/auth/position-history",
     *     summary="Membuat data position history",
     *     description="Mengirim data position history",
     *     tags={"Profil_Riwayat_hidup-Position"},
     *     security={{"BearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         content={
     *             @OA\MediaType(
     *                 mediaType="multipart/form-data",
     *                 @OA\Schema(
     *                     required={"poshistory_start","poshistory_end","poshistory_desc"},
     *                     @OA\Property(property="poshistory_start", type="string", example="2010-09-30"),
     *                     @OA\Property(property="poshistory_end", type="string", example="2010-09-30"),
     *                     @OA\Property(property="poshistory_desc", type="string", example="Kadinas"),
     *                 )
     *             )
     *         }
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Data position history berhasil disimpan",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="success")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validasi data  gagal",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=false),
     *             @OA\Property(property="error", type="object", additionalProperties={"type": "string"})
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        $this->databaseSwitcher->switchDatabaseFromToken(new poshistory());
        $validator = Validator::make($request->all(), [
            'poshistory_start' => 'required|string',
            'poshistory_end' => 'required|string',
            'poshistory_desc' => 'required|string',
            'poshistory_employee_id' => 'nullable',
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

        $data = poshistory::create([
            'poshistory_start' => $request->poshistory_start,
            'poshistory_end' => $request->poshistory_end,
            'poshistory_desc' => $request->poshistory_desc,
            'poshistory_employee_id' => $employee,
            'sekolah_id' => $sekolah
        ]);

        return response()->json([
            'is_correct' => true,
            'message' => 'success'
        ], 200);
    }

    /**
     * @OA\Delete(
     *     path="/api/auth/position-history/{position_id}",
     *     summary="Delete position history",
     *     description="Menghapus data position history berdasarkan ID",
     *     tags={"Profil_Riwayat_hidup-Position"},
     *     security={{"BearerAuth": {}}},
     *     @OA\Parameter(
     *         name="position_id",
     *         in="path",
     *         required=true,
     *         description="ID position untuk menghapus data ",
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
    public function destroy($poshistory_id)
    {
        $this->databaseSwitcher->switchDatabaseFromToken(new poshistory());
        $user = auth()->user();
        $data = poshistory::where('poshistory_id', $poshistory_id)->where('poshistory_employee_id', $user->employee_id)->first();

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
            ]);
        }
    }

    /**
     * @OA\Patch(
     *     path="/api/auth/position-history/{position_id}",
     *     summary="update data position history",
     *     description="Update data position history",
     *     tags={"Profil_Riwayat_hidup-Position"},
     *     security={{"BearerAuth": {}}},
     *      *     @OA\Parameter(
     *         name="position_id",
     *         in="path",
     *         required=true,
     *         description="ID untuk memperbarui data",
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
     *                     required={"poshistory_start","poshistory_end","poshistory_desc"},
     *                     @OA\Property(property="poshistory_start", type="string", example="2010-09-30"),
     *                     @OA\Property(property="poshistory_end", type="string", example="2010-09-30"),
     *                     @OA\Property(property="poshistory_desc", type="string", example="Kadinas"),
     *                 )
     *             )
     *         }
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Data position history berhasil diupate",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="success")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validasi data gagal",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=false),
     *             @OA\Property(property="error", type="object", additionalProperties={"type": "string"})
     *         )
     *     )
     * )
     */
    public function update(Request $request, $poshistory_id)
    {
        $this->databaseSwitcher->switchDatabaseFromToken(new poshistory());
        $user = auth()->user();
        $data = poshistory::where('poshistory_id', $poshistory_id)->where('poshistory_employee_id', $user->employee_id)->first();

        if ($data) {
            $validator = Validator::make($request->all(), [
                'poshistory_start' => 'required|string',
                'poshistory_end' => 'required|string',
                'poshistory_desc' => 'required|string',
                'poshistory_employee_id' => 'nullable',
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

            $data->poshistory_start = $request->input('poshistory_start');
            $data->poshistory_end = $request->input('poshistory_end');
            $data->poshistory_desc = $request->input('poshistory_desc');
            $data->poshistory_employee_id = $employee;
            $data->sekolah_id = $sekolah;
            $data->save();
            return response()->json([
                'is_correct' => true,
                'message' => 'success'
            ]);
        } else {
            return response()->json([
                'is_correct' => false,
                'message' => 'not found'
            ]);
        }
    }
}
