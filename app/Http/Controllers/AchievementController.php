<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\Request;
use App\Services\DatabaseSwitcher;
use App\Models\EmployeeAchievement;

use function PHPUnit\Framework\isNull;
use Illuminate\Support\Facades\Validator;

class AchievementController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/auth/employee-achievement",
     *     summary="Get data employee-achievement",
     *     description="Mengambil semua data achievement berdasarkan user login",
     *     tags={"Profil_Riwayat_hidup-Achievement"},
     *     security={{"BearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Data area absensi berhasil diambil",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="achievement_id", type="string", example="1"),
     *                     @OA\Property(property="achievement_year", type="string", example="2024"),
     *                     @OA\Property(property="achievement_name", type="string", example="pegawai terbaik"),
     *                     @OA\Property(property="achievement_employee_id", type="string", example="118"),
     *                     @OA\Property(property="sekolah_id", type="string", example="5"),
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
        // $this->databaseSwitcher->switchDatabaseFromToken(new EmployeeAchievement());
        $user = auth()->user();
        $data = EmployeeAchievement::where('achievement_employee_id', $user->employee_id)->get();
        // dd($data);
        if ($data->isEmpty()) {
            return response()->json([
                'is_correct' => false,
                'message' => 'data belum tersedia'
            ]);
        }

        return response()->json([
            'is_correct' => true,
            'massage' => 'success',
            'data' => $data
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/auth/employee-achievement",
     *     summary="Membuat data achievement",
     *     description="Mengirim data achievement",
     *     tags={"Profil_Riwayat_hidup-Achievement"},
     *     security={{"BearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         content={
     *             @OA\MediaType(
     *                 mediaType="multipart/form-data",
     *                 @OA\Schema(
     *                     required={"achievement_name","achievement_year"},
     *                     @OA\Property(property="achievement_name", type="string", example="guru terbaik", description="nama penghargaan"),
     *                     @OA\Property(property="achievement_year", type="string", format="date", example="2024", description="Tahun (YYYY)"),
     *                 )
     *             )
     *         }
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Data absensi berhasil disimpan",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="success")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validasi data absensi gagal",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=false),
     *             @OA\Property(property="error", type="object", additionalProperties={"type": "string"})
     *         )
     *     )
     * )
     */


    /**
     *  @OA\Get(
     * path="/api/auth/employee-achievement/{id}",
     * summary="Get data detail achievement history",
     * description="Mengambil detail data achievement history berdasarkan user login",
     * tags={"Profil_Riwayat_hidup-Achievement"},
     * security={{"BearerAuth": {}}},
     * @OA\Parameter(
     *     name="id",
     *     in="path",
     *    description="ID dari achievement yang ingin diambil",
     *    required=true,
     *    @OA\Schema(
     *           type="integer",
     *          example=12
     *       )
     *  ),
     * @OA\Response(
     *     response=200,
     *     description="Data achievement history berhasil diambil",
     *     @OA\JsonContent(
     *         @OA\Property(property="is_correct", type="boolean", example=true),
     *          @OA\Property(
     *             property="data",
     *              type="array",
     *              @OA\Items(
     *                  @OA\Property(property="achievement_id", type="integer", example=1),
     *                  @OA\Property(property="achievement_year", type="string", format="date", example="2024"),
     *                  @OA\Property(property="achievement_name", type="string", format="date", example="2024-06-24"),
     *                  @OA\Property(property="achievement_employee_id", type="integer", example=118),
     *           )
     *      )
     *  )
     * ),
     *  @OA\Response(
     *        response=404,
     *       description="Achievement history not found",
     *       @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Achivement  history not found")
     *           )
     *        )
     *    )
     */
    public function detail($achievement_id)
    {
        $this->databaseSwitcher->switchDatabaseFromToken(new EmployeeAchievement());
        $user = auth()->user();

        $data = EmployeeAchievement::where('achievement_id', $achievement_id)->where('achievement_employee_id', $user->employee_id)->first();
        if ($data) {
            $data = [
                "achievement_id" => $data->achievement_id,
                "achievement_year" => $data->achievement_year,
                "achievement_name" => $data->achievement_name,
                "achievement_employee_id" => $data->achievement_employee_id,
            ];
            return response()->json([
                'is_correct' => true,
                'message' => 'success',
                'data' => $data
            ], 200);
        } else {
            return response()->json([
                'is_correct' => false,
                'message' => 'achievement history not found'
            ], 404);
        }
    }
    public function store(Request $request)
    {
        //$this->databaseSwitcher->switchDatabaseFromToken(new EmployeeAchievement());
        $validator = Validator::make($request->all(), [
            'achievement_year' => 'required|numeric',
            'achievement_name' => 'required|string',
            'achievement_employee_id' => 'nullable',
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

        $data = EmployeeAchievement::create([
            'achievement_year' => $request->achievement_year,
            'achievement_name' => $request->achievement_name,
            'achievement_employee_id' => $employee,
            'sekolah_id' => $sekolah
        ]);

        return response()->json([
            'is_correct' => true,
            'message' => 'success'
        ], 200);
    }

    /**
     * @OA\Delete(
     *     path="/api/auth/employee-achievement/{achievement_id}",
     *     summary="Delete achievement",
     *     description="Menghapus data achivement berdasarkan ID",
     *     tags={"Profil_Riwayat_hidup-Achievement"},
     *     security={{"BearerAuth": {}}},
     *     @OA\Parameter(
     *         name="achievement_id",
     *         in="path",
     *         required=true,
     *         description="ID achievement untuk menghapus data ",
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
    public function destroy($achievement_id)
    {
        //$this->databaseSwitcher->switchDatabaseFromToken(new EmployeeAchievement());
        $user = auth()->user();
        //dd($user->employee_id);
        $data = EmployeeAchievement::where('achievement_id', $achievement_id)->where('achievement_employee_id', $user->employee_id)->first();
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
     *     path="/api/auth/employee-achievement/{achievement_id}",
     *     summary="Update Achievement",
     *     description="Memperbarui sebagian data laporan achievement berdasarkan ID",
     *     tags={"Profil_Riwayat_hidup-Achievement"},
     *     security={{"BearerAuth": {}}},
     *     @OA\Parameter(
     *         name="achievement_id",
     *         in="path",
     *         required=true,
     *         description="ID achievement untuk memperbarui data",
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
     *                     required={"achievement_name","achievement_year"},
     *                     @OA\Property(property="achievement_name", type="string", example="guru terbaik", description="Nama penghargaan"),
     *                     @OA\Property(property="achievement_year", type="string", format="date", example="2024", description="Tahun (YYYY)")
     *                 )
     *             )
     *         }
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Data berhasil diupdate",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Data berhasil diupdate")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Data tidak ditemukan",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="data tidak ditemukan")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validasi gagal",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation error"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(property="achievement_name", type="array", @OA\Items(type="string", example="The achievement_name field is required.")),
     *                 @OA\Property(property="achievement_year", type="array", @OA\Items(type="string", example="The achievement_year field is required."))
     *             )
     *         )
     *     )
     * )
     */

    public function update(Request $request, $achievement_id)
    {
        //$this->databaseSwitcher->switchDatabaseFromToken(new EmployeeAchievement());
        $user = auth()->user();
        $data = EmployeeAchievement::where('achievement_id', $achievement_id)->where('achievement_employee_id', $user->employee_id)->first();

        if ($data) {
            $validator = Validator::make($request->all(), [
                'achievement_year' => 'required|numeric',
                'achievement_name' => 'required|string',
                'achievement_employee_id' => 'nullable',
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

            $data->achievement_year = $request->input('achievement_year');
            $data->achievement_name = $request->input('achievement_name');
            $data->achievement_employee_id = $employee;
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
