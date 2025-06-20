<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Student;
use Illuminate\Http\Request;
use App\Services\DatabaseSwitcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class PasswordController extends Controller
{
    /**
     * @OA\Patch(
     *     path="/api/auth/password-update",
     *     summary="Update Password User",
     *     description="Memperbarui Password",
     *     tags={"Profil"},
     *     security={{"BearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Perbarui password",
     *         @OA\JsonContent(
     *             @OA\Property(property="current_password", type="string",  example="fikri", description="nama user"),
     *             @OA\Property(property="employee_password", type="string", example="fikri@gmail.com", description="email user"),
     *             @OA\Property(property="employee_password_confirmation", type="string", example="089727236", description="Phone user"),
     *         )
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
     *         )
     *     )
     * )
     */

    // protected $databaseSwitcher;

    // // Inject DatabaseSwitcher di constructor
    // public function __construct(DatabaseSwitcher $databaseSwitcher)
    // {
    //     $this->databaseSwitcher = $databaseSwitcher;
    // }

    public function update(Request $request)
    {
        // $this->databaseSwitcher->switchDatabaseFromToken(new Employee());

        // Validasi input
        $validator = Validator::make($request->all(), [
            'current_password' => 'required',
            'student_password' => 'required|string|confirmed'
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = auth()->user();
        $student_id = $user->student_id;

        // Verifikasi password saat ini dengan MD5
        if (md5($request->current_password) !== $user->student_password) {
            return response()->json([
                'is_correct' => false,
                'message' => 'Password saat ini tidak cocok.'
            ], 200);
        }

        // Hash password baru dengan MD5 dan update
        $data_update = [
            'student_password' => md5($request->student_password)
        ];

        // Update password di database
        // Employee::where('employee_id', $employee_id)->update($data_update);

        Student::where('student_id', $student_id)->update($data_update);

        return response()->json([
            'is_correct' => true,
            'message' => 'success'
        ], 200);
    }
}
