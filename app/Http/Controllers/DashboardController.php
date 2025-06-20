<?php

namespace App\Http\Controllers;

use App\Models\Banking;
use Carbon\Carbon;
// use App\Models\Employee;
use App\Models\Employee;
use App\Models\DataAbsen;
use App\Models\Information;
use App\Models\Setting;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;

class DashboardController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/dashboard",
     *     summary="Get data dashboard",
     *     description="Mengambil semua data didashboard berdasarkan user login",
     *     tags={"Dashboard"},
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
     *                     @OA\Property(property="sekolah", type="string", example="nama sekolah"),
     *                     @OA\Property(property="foto", type="string", example="foto.jpg"),
     *                     @OA\Property(property="name", type="string", example="nama pegawai"),
     *                     @OA\Property(property="jabatan", type="string", example="jabatan"),
     *                     @OA\Property(property="datang", type="string", example="datang"),
     *                     @OA\Property(property="pulang", type="string", example="pulang"),
     *                     @OA\Property(
     *                     property="information",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="title", type="string", example="Pengumuman Penting"),
     *                         @OA\Property(property="tanggal", type="string", example="2024-10-25"),
     *                         @OA\Property(property="information_img", type="string", example="image.jpg")
     *                     )
     *                 )
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

    public function index()
    {
        //claim payload

        $token = JWTAuth::parseToken();

        // Get the token payload
        $claims = $token->getPayload();

        $schoolName = $claims->get('schoolName');
        $studentId = $claims->get('student');
        $folder = $claims->get('folder');

        $user = Student::where('student_id', $studentId)->first();
        //   dd($user->student_id);

        $banking_debit = Banking::where('banking_student_id', $user->student_id)->sum('banking_debit');
        //dd($banking_debit);
        $banking_kredit = Banking::where('banking_student_id', $user->student_id)->sum('banking_kredit');
        // dd($banking_kredit);

        //paket
        $paket = Setting::where('setting_name', 'setting_package')->first();

        $saldoTabungan = $banking_debit - $banking_kredit;
        $information = Information::latest('information_input_date')->take(3)->get();
        $informasi = $information->map(function ($info) use ($folder) {
            return [
                'id' => $info->information_id,
                'title' => $info->information_title,
                'tanggal' => Carbon::parse($info->information_input_date)->format('Y-m-d'),
                'information_img' => "https://$folder.ebimbel.co.id/uploads/information/" . $info->information_img
            ];
        });

        $whiteList = Setting::where('setting_name', 'whitelist')->first();
        $getWhiteList = $whiteList?->setting_value ?? '';
        $whiteListArray = $getWhiteList ? array_map('intval', explode(',', $getWhiteList)) : [];

        $blackList = Setting::where('setting_name', 'blacklist')->first();
        $getBlackList = $blackList?->setting_value ?? '';
        $blackListArray = $getBlackList ? array_map('intval', explode(',', $getBlackList)) : [];
        return response()->json([
            'is_correct' => true,
            'message' => 'success',
            // 'paket' => (int)$paket->setting_value ?? null,
            'paket' => $paket ? (int)$paket->setting_value : null,
            'blacklist' => $blackListArray,
            'whitelist' => $whiteListArray,
            'dashboard' => [
                'sekolah' => $schoolName,
                'foto' =>  "https://" . $folder . ".ebimbel.co.id/uploads/student/" . $user->student_img, // perbaikan
                // 'foto' => $user->sekolah->foto_sekolah ?? null,
                //'foto' => asset($user->student_img),
                'name' => $user->student_full_name,
                //'saldo_tabungan' => 'Rp ' . number_format($saldoTabungan, 0, ',', '.'),
                'saldo_tabungan' => (int) $saldoTabungan,
                'kelas' => $user->kelas->class_name,
                'information' => $informasi,
            ]
        ], 200);
    }

    public function logout()
    {
        JWTAuth::invalidate(JWTAuth::getToken());
        return response()->json([
            'is_correct' => true,
            'message' => 'success'
        ], 200);
    }
}
