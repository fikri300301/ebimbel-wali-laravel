<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\otp;
use App\Models\Sekolah;
use App\Models\Employee;
use App\Models\Student;
use Illuminate\Http\Request;
use App\Services\WhatsappService;
use App\Services\DatabaseSwitcher;
use App\Services\WhatsappServiceOtp;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Validator;

class OtpController extends Controller
{

    /**
     * @OA\Post(
     *     path="/api/lupa-password",
     *     tags={"forgot password"},
     *     summary="Forgot Password",
     *     description="Lupa password masukkan kode sekolah dan nip ",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"kode_sekolah","employee_nip"},
     *             @OA\Property(property="kode_sekolah", type="string", format="string", example="2012001"),
     *             @OA\Property(property="employee_nip", type="string", format="string", example="123456"),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful OTP dikirim di wa",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="success"),
     *             @OA\Property(property="token", type="string", example="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvYXBpL2xvZ2luIiwiaWF0IjoxNzI5NTY4MTIzLCJleHAiOjE3Mjk1NzE3MjMsIm5iZiI6MTcyOTU2ODEyMywianRpIjoiR1VDR2Z1Y3pPaUtzWlp5QiIsInN1YiI6IjExOCIsInBydiI6IjMyOTYzYTYwNmMyZjE3MWYxYzE0MzMxZTc2OTc2NmNkNTkxMmVkMTUifQ.NuBcDlpsxa8x9XJdsCWeteRmh3Neqa3QlD8ueBmbfec")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Data sekolah not found"),
     *     @OA\Response(response=400, description="Bad Request"),
     * )
     */
    public function otp(Request $request)
    {
        $credentials = $request->only('student_nis', 'kode_sekolah');

        $sekolah = Sekolah::where('kode_sekolah', $credentials['kode_sekolah'])->first();
        if (!$sekolah) {
            return response()->json([
                'is_correct' => false,
                'message' => 'data sekolah not found'
            ], 404);
        }


        $sekolahModel = new Sekolah();
        $sekolahModel->setDatabaseName($sekolah->db);
        $sekolahModel->switchDatabase();
        //  dd($sekolahModel);

        //cek nip ada atau tidak
        $user = Student::where('student_nis', $credentials['student_nis'])->first();

        $payload = [
            'db' => $sekolahModel->getDatabaseName(),
            'student_nis' => $user->student_nis,
            'restricted_to' => ['verifikasi', 'update-password']
        ];

        $token = JWTAuth::claims($payload)->fromUser($user);

        if ($user) {

            $validator = Validator::make($request->all(), [
                'nis' => 'nullable',
                'code' => 'nullable',
                'expired' => 'nullable',
                'status' => 'nullable'
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'is_correct' => false,
                    'message' => $validator->errors()
                ], 404);
            }

            //isi nya nanti wib,wit,wita
            $waktu = $user->waktu_indonesia;
            //dd($waktu);
            $zonaWaktu = '';
            if ($waktu == 'WIB') {
                $zonaWaktu = 'Asia/Jakarta';
            }
            if ($waktu == 'WIT') {
                $zonaWaktu = 'Asia/Jayapura';
            }
            if ($waktu == 'WITA') {
                $zonaWaktu = 'Asia/Makassar';
            }
            // dd($zonaWaktu);


            $otpPlain = rand(100000, 999999);
            $otpHashed = md5($otpPlain);
            $expirationTime = Carbon::now($zonaWaktu)->addMinutes(5);


            $otp = otp::create([
                'nis' => $user->student_nis,
                'code' => $otpHashed,
                'expired' => $expirationTime,
                'status' => 0
            ]);
            $pesan = '';

            $whatsappService = new WhatsappServiceOtp();
            $nowa = $user->student_parent_phone;
            $pesan .= "Kode OTP Anda untuk reset password adalah $otpPlain \n";
            $pesan .= "akan berakhir pada $expirationTime";
            $whatsappService->kirimpesan($nowa, $pesan);

            return response()->json([
                'is_correct' => true,
                'message' => 'success',
                'token' => $token
            ]);
        } else {
            return response()->json([
                'is_correct' => false,
                'message' => 'nis tidak ada'
            ]);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/verifikasi",
     *     tags={"forgot password"},
     *     summary="Verifikasi OTP",
     *     security={{"BearerAuth":{}}},
     *     description="Masukkan kode OTP ",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"otp"},
     *             @OA\Property(property="otp", type="string", format="string", example="2012001"),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful OTP dikirim di wa",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="success"),
     *         )
     *     ),
     *     @OA\Response(response=404, description="OTP tidak ditemukan"),
     *     @OA\Response(response=400, description="Bad Request"),
     * )
     */
    public function VerifyOtp(Request $request)
    {
        $token = JWTAuth::parseToken();
        $claims = $token->getPayload();

        $dbname = $claims->get('db');
        $studentNis = $claims->get('student_nis');

        // Configure the dynamic database connection
        Config::set("database.connections.{$dbname}", [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => $dbname,
            'username' => env('DB_USERNAME', 'your_username'),
            'password' => env('DB_PASSWORD', 'your_password'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ]);

        // Purge the current connection and use the new one
        DB::purge($dbname);
        DB::reconnect($dbname);

        $validator = Validator::make($request->all(), [
            'otp' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'is_correct' => false,
                'message' => 'OTP wajib diisi'
            ], 400);
        }

        $cekOtp = DB::connection($dbname)
            ->table('otp')
            ->where('code', md5($request->otp))
            ->first();
        //isi nya nanti wib,wit,wita
        $user = DB::connection($dbname)->table('student')->where('student_nis',   $studentNis)->first();
        $waktu = $user->waktu_indonesia;

        $zonaWaktu = '';
        if ($waktu == 'WIB') {
            $zonaWaktu = 'Asia/Jakarta';
        } elseif ($waktu == 'WIT') {
            $zonaWaktu = 'Asia/Jayapura';
        } elseif ($waktu == 'WITA') {
            $zonaWaktu = 'Asia/Makassar';
        }
        // dd($zonaWaktu);

        if ($cekOtp) {
            $currentTime = Carbon::now($zonaWaktu);
            $expiredTime = Carbon::parse($cekOtp->expired, $zonaWaktu);

            if ($currentTime->greaterThan($expiredTime)) {
                return response()->json([
                    'is_correct' => false,
                    'message' => 'OTP sudah kedaluwarsa'
                ], 400);
            }

            //update field status bernilai 1 jika sudah di cek benar
            DB::connection($dbname)
                ->table('otp')
                ->where('code', md5($request->otp))
                ->update(['status' => 1]);

            return response()->json([
                'is_correct' => true,
                'message' => 'success'
            ], 200);
        } else {
            return response()->json([
                'is_correct' => false,
                'message' => 'OTP tidak ditemukan'
            ], 404);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/update-password",
     *     tags={"forgot password"},
     *     summary="update Password",
     *     security={{"BearerAuth":{}}},
     *     description="Update password baru ",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"employee_password","employee_password_confirmed"},
     *             @OA\Property(property="employee_password", type="string", format="password", example="123456"),
     *             @OA\Property(property="employee_password_confirmation", type="string", format="password", example="123456"),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Password berhasil diganti silahkan login kembali",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="password berhasil diganti silahkan login kembali"),
     *         )
     *     ),
     *     @OA\Response(response=404, description="token has expired"),
     *     @OA\Response(response=400, description="The employee password field corfirmation does not match"),
     * )
     */
    public function update(Request $request)
    {
        $token = JWTAuth::parseToken();
        $claims = $token->getPayload();

        // Mengambil informasi dari klaim token
        $dbname = $claims->get('db');
        $studentNis = $claims->get('student_nis');

        // Menetapkan konfigurasi koneksi database dinamis
        Config::set("database.connections.{$dbname}", [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => $dbname,
            'username' => env('DB_USERNAME', 'your_username'),
            'password' => env('DB_PASSWORD', 'your_password'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ]);

        // Membersihkan dan menghubungkan kembali koneksi ke database yang sesuai
        DB::purge($dbname);
        DB::reconnect($dbname);

        // Validasi input dari pengguna
        $validator = Validator::make($request->all(), [
            'student_password' => 'required|min:6|confirmed'
        ]);

        // Menangani jika validasi gagal
        if ($validator->fails()) {
            return response()->json([
                'is_correct' => false,
                'message' => $validator->errors()
            ], 400);
        }

        // Mencari data pengguna berdasarkan employee_nip
        $user = DB::connection($dbname)->table('student')->where('student_nis', $studentNis)->first();

        // Menangani jika pengguna tidak ditemukan
        if (!$user) {
            return response()->json([
                'is_correct' => false,
                'message' => 'Pengguna tidak ditemukan'
            ]);
        }

        // Mengambil status OTP terbaru yang valid berdasarkan nis dan status = 1
        $status = DB::connection($dbname)->table('otp')
            ->where('nis', $studentNis)
            ->where('status', 1)
            ->orderBy('expired', 'desc')
            ->first();

        // Menangani jika status OTP tidak valid atau belum terverifikasi
        if (!$status || $status->status == 0) {
            return response()->json([
                'is_correct' => false,
                'message' => 'Status OTP tidak valid atau belum terverifikasi'
            ]);
        }

        // Pembaruan password jika OTP valid
        DB::connection($dbname)->table('student')->where('student_nis', $studentNis)->update([
            'student_password' => md5($request->student_password)
        ]);

        // Menyediakan respons sukses
        return response()->json([
            'is_correct' => true,
            'message' => 'Password berhasil diganti, silakan login kembali'
        ]);
    }
}
