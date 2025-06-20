<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\EmployeeEducation;
use App\Services\DatabaseSwitcher;
use GrahamCampbell\ResultType\Success;
use Illuminate\Support\Facades\Validator;

class EducationController extends Controller
{

    /**
     * @OA\Get(
     *     path="/api/auth/employee-education",
     *     summary="Get data employee-education",
     *     description="Mengambil semua data education berdasarkan user login",
     *     tags={"Profil_Riwayat_hidup-Education"},
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
     *                     @OA\Property(property="education_id", type="string", example="1"),
     *                     @OA\Property(property="education_start", type="string", example="2024"),
     *                     @OA\Property(property="education_end", type="string", example="2025"),
     *                     @OA\Property(property="education_name", type="string", example="UGM"),
     *                     @OA\Property(property="education_location", type="string", example="Jogjakarta"),
     *                     @OA\Property(property="education_employee_id", type="string", example="118"),
     *                     @OA\Property(property="sekolah_id", type="string", example="1"),
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
        //$this->databaseSwitcher->switchDatabaseFromToken(new EmployeeEducation());
        $user = auth()->user();
        $data = EmployeeEducation::where('education_employee_id', $user->employee_id)->get();

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
     *     path="/api/auth/employee-education",
     *     summary="Membuat data employee-education",
     *     description="Mengirim data employee-education",
     *     tags={"Profil_Riwayat_hidup-Education"},
     *     security={{"BearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         content={
     *             @OA\MediaType(
     *                 mediaType="multipart/form-data",
     *                 @OA\Schema(
     *                     required={"education_start","education_end","education_name","education_location" },
     *                     @OA\Property(property="education_start", type="string", example="2024"),
     *                     @OA\Property(property="education_end", type="string", example="2025"),
     *                     @OA\Property(property="education_name", type="string", example="UGM"),
     *                     @OA\Property(property="education_location", type="string", example="Jogjakarta"),

     *                 )
     *             )
     *         }
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Data education berhasil disimpan",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="success")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validasi data education gagal",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=false),
     *             @OA\Property(property="error", type="object", additionalProperties={"type": "string"})
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        //$this->databaseSwitcher->switchDatabaseFromToken(new EmployeeEducation());
        $validator = Validator::make($request->all(), [
            'education_start' => 'required|numeric',
            'education_end' => 'required|numeric',
            'education_name' => 'required|string',
            'education_location' => 'required|string',
            'education_employee_id' => 'nullable',
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

        $data = EmployeeEducation::create([
            'education_start' => $request->education_start,
            'education_end' => $request->education_end,
            'education_name' => $request->education_name,
            'education_location' => $request->education_location,
            'education_employee_id' => $employee,
            'sekolah_id' => $sekolah
        ]);

        return response()->json([
            'is_correct' => true,
            'message' => 'success'
        ], 200);
    }


    /**
     * @OA\Delete(
     *     path="/api/auth/employee-education/{eduction_id}",
     *     summary="Delete education",
     *     description="Menghapus data educatioin berdasarkan ID",
     *     tags={"Profil_Riwayat_hidup-Education"},
     *     security={{"BearerAuth": {}}},
     *     @OA\Parameter(
     *         name="eduction_id",
     *         in="path",
     *         required=true,
     *         description="ID edukasi untuk menghapus data ",
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
    public function destroy($education_id)
    {
        //$this->databaseSwitcher->switchDatabaseFromToken(new EmployeeEducation());
        $user = auth()->user();
        $data = EmployeeEducation::where('education_id', $education_id)->where('education_employee_id', $user->employee_id)->first();

        if ($data) {
            $data->delete();
            return response()->json([
                'is_correct' => true,
                'message' => 'success'
            ]);
        } else {
            return response()->json([
                'is_correct' => false,
                'message' => 'data tidak ditemukan'
            ]);
        }
    }

    /**
     * @OA\Patch(
     *     path="/api/auth/employee-education/{eduction_id}",
     *     summary="Membuat data employee-education",
     *     description="Mengirim data employee-education",
     *     tags={"Profil_Riwayat_hidup-Education"},
     *     security={{"BearerAuth": {}}},
     *      *     @OA\Parameter(
     *         name="eduction_id",
     *         in="path",
     *         required=true,
     *         description="ID education untuk memperbarui data",
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
     *                     @OA\Property(property="education_start", type="string", example="2024"),
     *                     @OA\Property(property="education_end", type="string", example="2025"),
     *                     @OA\Property(property="education_name", type="string", example="UGM"),
     *                     @OA\Property(property="education_location", type="string", example="Jogjakarta"),

     *                 )
     *             )
     *         }
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Data education berhasil disimpan",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="success")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validasi data education gagal",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=false),
     *             @OA\Property(property="error", type="object", additionalProperties={"type": "string"})
     *         )
     *     )
     * )
     */
    public function update(Request $request, $education_id)
    {
        //$this->databaseSwitcher->switchDatabaseFromToken(new EmployeeEducation());
        $user = auth()->user();
        $data = EmployeeEducation::where('education_id', $education_id)->where('education_employee_id', $user->employee_id)->first();

        if ($data) {
            $validator = Validator::make($request->all(), [
                'education_start' => 'required|numeric',
                'education_end' => 'required|numeric',
                'education_name' => 'required|string',
                'education_location' => 'required|string',
                'education_employee_id' => 'nullable',
                'sekolah_id' => 'nullable'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'is_correct' => false,
                    'message' => $validator->errors()
                ]);
            }

            $user = auth()->user();
            $employee = $user->employee_id;
            $sekolah = $user->sekolah_id;

            $data->education_start = $request->input('education_start');
            $data->education_end = $request->input('education_end');
            $data->education_name = $request->input('education_name');
            $data->education_location = $request->input('education_location');
            $data->education_employee_id = $employee;
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
