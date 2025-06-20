<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\teachingHistory;
use App\Services\DatabaseSwitcher;
use Illuminate\Support\Facades\Validator;

class TeachingHistoryController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/auth/teaching-history",
     *     summary="Get data teaching",
     *     description="Mengambil semua data teaching berdasarkan user login",
     *     tags={"Profil_Riwayat_hidup-Teaching"},
     *     security={{"BearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Data teaching berhasil diambil",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="teaching_id", type="string", example="1"),
     *                     @OA\Property(property="teaching_start", type="string", example="2000"),
     *                     @OA\Property(property="teaching_end", type="string", example="2024"),
     *                     @OA\Property(property="teaching_lesson", type="string", example="IPA"),
     *                     @OA\Property(property="teaching_desc", type="string", example="ilmu pengetahuan alam"),
     *                     @OA\Property(property="teaching_employee_id", type="integer", example="118"),
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
        // $this->databaseSwitcher->switchDatabaseFromToken(new teachingHistory());
        $user = auth()->user();
        $data = teachingHistory::where('teaching_employee_id', $user->employee_id)->get();

        if ($data->isEmpty()) {
            return response()->json([
                'is_correct' => false,
                'message' => 'data belum tersedia'
            ]);
        }

        return response()->json([
            'is_correct' => true,
            'message' => 'success',
            'data' => $data
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/auth/teaching-history",
     *     summary="Membuat data employee-fam",
     *     description="Mengirim data teaching history",
     *     tags={"Profil_Riwayat_hidup-Teaching"},
     *     security={{"BearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         content={
     *             @OA\MediaType(
     *                 mediaType="multipart/form-data",
     *                 @OA\Schema(
     *                     required={"teaching_start","teaching_end","teaching_lesson","teaching_desc"},
     *                     @OA\Property(property="teaching_start", type="string", example="2010-09-30"),
     *                     @OA\Property(property="teaching_end", type="string", example="2010-09-30"),
     *                     @OA\Property(property="teaching_lesson", type="string", example="IPA"),
     *                     @OA\Property(property="teaching_desc", type="string", example="pertukangan"),
     *                 )
     *             )
     *         }
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Data teaching history berhasil disimpan",
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
        // $this->databaseSwitcher->switchDatabaseFromToken(new teachingHistory());
        $validator = Validator::make($request->all(), [
            'teaching_start' => 'required|string',
            'teaching_end' => 'required|string',
            'teaching_lesson' => 'required|string',
            'teaching_desc' => 'required|string',
            'teaching_employee_id' => 'nullable',
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

        $data = teachingHistory::create([
            'teaching_start' => $request->teaching_start,
            'teaching_end' => $request->teaching_end,
            'teaching_lesson' => $request->teaching_lesson,
            'teaching_desc' => $request->teaching_desc,
            'teaching_employee_id' => $employee,
            'sekolah_id' => $sekolah
        ]);

        return response()->json([
            'is_correct' => true,
            'message' => 'success'
        ], 200);
    }

    /**
     * @OA\Delete(
     *     path="/api/auth/teaching-history/{teaching_id}",
     *     summary="Delete teaching history",
     *     description="Menghapus data teaching history berdasarkan ID",
     *     tags={"Profil_Riwayat_hidup-Teaching"},
     *     security={{"BearerAuth": {}}},
     *     @OA\Parameter(
     *         name="teaching_id",
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
    public function destroy($teaching_id)
    {
        // $this->databaseSwitcher->switchDatabaseFromToken(new teachingHistory());
        $user = auth()->user();
        $data = teachingHistory::where('teaching_id', $teaching_id)->where('teaching_employee_id', $user->employee_id)->first();

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
            ], 200);
        }
    }


    /**
     * @OA\Patch(
     *     path="/api/auth/teaching-history/{teaching_id}",
     *     summary="update data teaching history",
     *     description="Update data teaching history",
     *     tags={"Profil_Riwayat_hidup-Teaching"},
     *     security={{"BearerAuth": {}}},
     *      *     @OA\Parameter(
     *         name="teaching_id",
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
     *                     required={"teaching_start","teaching_end","teaching_lesson","teaching_desc" },
     *                     @OA\Property(property="teaching_start", type="string", example="2010-09-30"),
     *                     @OA\Property(property="teaching_end", type="string", example="2010-09-30"),
     *                     @OA\Property(property="teaching_lesson", type="string", example="IPA"),
     *                     @OA\Property(property="teaching_desc", type="string", example="pertukangan"),
     *                 )
     *             )
     *         }
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Data teaching history berhasil diupate",
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
    public function update(Request $request, $teaching_id)
    {
        // $this->databaseSwitcher->switchDatabaseFromToken(new teachingHistory());
        $user = auth()->user();
        $data = teachingHistory::where('teaching_id', $teaching_id)->where('teaching_employee_id', $user->employee_id)->first();
        // dd($data);

        if ($data) {
            $validator = Validator::make($request->all(), [
                'teaching_start' => 'required|string',
                'teaching_end' => 'required|string',
                'teaching_lesson' => 'required|string',
                'teaching_desc' => 'required|string',
                'teaching_employee_id' => 'nullable',
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

            $data->teaching_start = $request->input('teaching_start');
            $data->teaching_end = $request->input('teaching_end');
            $data->teaching_lesson = $request->input('teaching_lesson');
            $data->teaching_desc = $request->input('teaching_desc');
            $data->teaching_employee_id = $employee;
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
