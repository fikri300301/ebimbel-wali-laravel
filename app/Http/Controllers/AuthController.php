<?php

namespace App\Http\Controllers;


use App\Models\major;
use App\Models\Sekolah;
use App\Models\Setting;
use App\Models\Student;
use App\Models\Employee;
use App\Models\Tahfidz;
use Illuminate\Http\Request;
use App\Services\DatabaseSwitcher;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;


class AuthController extends Controller
{
    protected $databaseSwitcher;

    public function __construct(DatabaseSwitcher $databaseSwitcher)
    {
        $this->databaseSwitcher = $databaseSwitcher;
    }

    /**
     * @OA\Post(
     *     path="/api/auth/login",
     *     tags={"Auth"},
     *     summary="Login user",
     *     description="Login user and return JWT token",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"kode_sekolah","password","employee_nip"},
     *             @OA\Property(property="kode_sekolah", type="string", format="string", example="2012001"),
     *             @OA\Property(property="employee_password", type="string", format="password", example="123456"),
     *             @OA\Property(property="employee_nip", type="string", format="string", example="123456"),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful login",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="success"),
     *             @OA\Property(property="kode_pesantren", type="integer", example=3),
     *             @OA\Property(property="employee_nip", type="string", example="123456"),
     *             @OA\Property(property="id_pegawai", type="integer", example=118),
     *             @OA\Property(property="waktu_indonesia", type="string", example="WIB"),
     *             @OA\Property(property="mode_absen", type="string", example="harian"),
     *             @OA\Property(property="token", type="string", example="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvYXBpL2xvZ2luIiwiaWF0IjoxNzI5NTY4MTIzLCJleHAiOjE3Mjk1NzE3MjMsIm5iZiI6MTcyOTU2ODEyMywianRpIjoiR1VDR2Z1Y3pPaUtzWlp5QiIsInN1YiI6IjExOCIsInBydiI6IjMyOTYzYTYwNmMyZjE3MWYxYzE0MzMxZTc2OTc2NmNkNTkxMmVkMTUifQ.NuBcDlpsxa8x9XJdsCWeteRmh3Neqa3QlD8ueBmbfec")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Invalid credentials"),
     *     @OA\Response(response=400, description="Bad Request"),
     * )
     */

    public function login(Request $request)
    {

        // Validasi input
        $credentials = $request->only('student_nis', 'student_password', 'kode_sekolah');

        // Cek apakah sekolah dengan kode_sekolah ada
        $sekolah = Sekolah::where('kode_sekolah', $credentials['kode_sekolah'])->first();

        if (!$sekolah) {
            return response()->json([
                'is_correct' => 'false',
                'error' => 'Sekolah tidak ditemukan.'
            ], 404);
        }

        // Switch database sesuai dengan nilai field 'db' di tabel 'sekolahs'
        $sekolahModel = new Sekolah();
        $sekolahModel->setDatabaseName($sekolah->db);
        $this->databaseSwitcher->switchDatabase($sekolah->db, $sekolahModel);


        $student = Student::where('student_nis', $credentials['student_nis'])->first();
        if (!$student) {
            return response()->json([
                'is_correct' => 'false',
                'message' => 'NIS tidak ditemukan'
            ], 401);
        }


        if (md5($credentials['student_password']) !== $student->student_password) {
            return response()->json([
                'is_correct' => 'false',
                'message' => 'Password salah'
            ], 401);
        }

        if (!$student instanceof \Tymon\JWTAuth\Contracts\JWTSubject) {
            return response()->json(['error' => 'Model tidak kompatibel dengan JWTSubject'], 500);
        }
        //  dd($sekolahModel->getDatabaseName());
        $payload = [
            'db' => $sekolahModel->getDatabaseName(),
            'schoolName' => $sekolah->nama_sekolah,
            'location' => $sekolah->location,
            'kode_sekolah' => $sekolah->kode_sekolah,
            'folder' => $sekolah->folder,
            'payment' => $sekolah->payment_gateway,
            'student' => $student->student_id,
            'waktu_idonesia' => $sekolah->waktu_indonesia
        ];
        //paket setting
        $noPaket = Setting::where('setting_name', 'setting_package')->first();
        //  dd($noPaket->setting_value);

        // Generate token
        $token = JWTAuth::claims($payload)->fromUser($student);
        // Cek koneksi aktif
        $currentConnection = DB::connection()->getDatabaseName();
        return response()->json([
            'is_correct' => true,
            'message' => 'success',
            'paket' => $noPaket->setting_value ?? null,
            'kode_pesantren' => $credentials['kode_sekolah'],
            'nama' => $student->student_full_name,
            'nama_pesantren' => $sekolah->nama_sekolah,
            'student_nis' => $student->student_nis,
            'student_class' => $student->kelas->class_name,
            'student_majors' => $student->major->majors_name,
            'student_id' => $student->student_id,
            'token' => $token,
            'current_database' => $currentConnection,
        ], 200);
    }



    /**
     * @OA\Post(
     *     path="/api/auth/me",
     *     summary="Get data me",
     *     tags={"Auth"},
     *     security={{"BearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="true."
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Token tidak valid."
     *     )
     * )
     */
    public function me(Request $request)
    {

        dd('test');
    }

    public function refresh()
    {
        return $this->respondWithToken(auth()->refresh());
    }


    public function logout()
    {
        JWTAuth::invalidate(JWTAuth::getToken());
        return response()->json([
            'is_correct' => true,
            'message' => 'success'
        ], 200);

        return response()->json(['message' => 'Successfully logged out']);
    }

    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 1
        ]);
    }
}
