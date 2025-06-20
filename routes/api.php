<?php

use App\Models\Tahfidz;
use App\Models\Tabungan;
use App\Models\FlipCallback;
use Illuminate\Http\Request;
use App\Models\PresensiAreaV;
use App\Models\PresensiStudent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DayController;
use App\Http\Controllers\OtpController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\IzinController;
use App\Http\Controllers\ClassController;
use App\Http\Controllers\KelasController;
use App\Http\Controllers\MajorController;
use App\Http\Controllers\MonthController;
use App\Http\Controllers\PaketController;
use App\Http\Controllers\ProveController;
use App\Http\Controllers\DonasiController;
use App\Http\Controllers\FamilyController;
//use App\Http\Controllers\JadwalController;
use App\Http\Controllers\JadwalController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\PeriodController;
use App\Http\Controllers\ProfilController;
use App\Http\Controllers\GetUserController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\SekolahController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\TahfidzController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\PositionController;
use App\Http\Controllers\SemesterController;
use App\Http\Controllers\TabunganController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DataAbsenController;
use App\Http\Controllers\DataWaktuController;
use App\Http\Controllers\EducationController;
use App\Http\Controllers\KesehatanController;
use App\Http\Controllers\KonselingController;
use App\Http\Controllers\KunjunganController;
use App\Http\Controllers\NotifikasiController;
use App\Http\Controllers\AchievementController;
use App\Http\Controllers\AreaAbsensiController;
use App\Http\Controllers\BniCallbackController;
use App\Http\Controllers\FlipChannelController;
use App\Http\Controllers\FlipPaymentController;
use App\Http\Controllers\InformationController;
use App\Http\Controllers\FlipCallbackController;
use App\Http\Controllers\HubunganKamiController;
use App\Http\Controllers\PendayagunaanController;
use App\Http\Controllers\PengajuanIzinController;
use App\Http\Controllers\PresensiAreaVController;
use App\Http\Controllers\CaraPembayaranController;
use App\Http\Controllers\IpaymuCallbackController;
use App\Http\Controllers\PresensiHarianController;
use App\Http\Controllers\PembayaranBebasController;
use App\Http\Controllers\PengajuanPulangController;
use App\Http\Controllers\PositionHistoryController;
use App\Http\Controllers\PresensiStudentController;
use App\Http\Controllers\TeachingHistoryController;
use App\Http\Controllers\WorkshopHistoryController;
use App\Http\Controllers\DashboardStudentController;
use App\Http\Controllers\RiwayatTransaksiController;
use App\Http\Controllers\PembayaranBulananController;
use App\Http\Controllers\PresensiPelajaranController;
use App\Http\Controllers\RingkasanPemabayaranController;
use App\Http\Controllers\LaporanAbsensiStudentController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });
//login
Route::post('/student/auth/login', [AuthController::class, 'login']);

Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/lupa-password', [OtpController::class, 'otp']);
Route::post('/verifikasi', [OtpController::class, 'VerifyOtp'])->name('verifikasi')->middleware('restrict.token.usage');
Route::post('/update-password', [OtpController::class, 'update'])->name('update-password')->middleware('restrict.token.usage');

Route::group(['middleware' => ['jwt.auth', 'restrict.token.usage'], 'prefix' => 'auth'], function ($router) {

    //   Route::post('/logout', [AuthController::class, 'logout']);
    //Route::post('/me', [AuthController::class, 'me']);
    Route::get('/profil', [ProfilController::class, 'index']);
    Route::post('/profil', [ProfilController::class, 'update']);
    Route::patch('/password-update', [PasswordController::class, 'update']);
});

//route untuk murid
Route::group(['middleware' => ['jwt.auth', 'restrict.token.usage']], function ($router) {
    Route::group(['prefix' => 'student'], function ($router) {
        Route::get('/dashboard', [DashboardStudentController::class, 'index']);
        Route::get('/logout', [DashboardStudentController::class, 'logout']);
        Route::post('/absen', [PresensiStudentController::class, 'store']);
        Route::post('/pulang', [PresensiStudentController::class, 'storePulang']);
        Route::post('/izin', [PresensiStudentController::class, 'storeIzin']);
        Route::get('/profil', [ProfilController::class, 'index']);
        Route::post('/profil', [ProfilController::class, 'update']);
        Route::get('/laporan-absensi-bulan', [LaporanAbsensiStudentController::class, 'index']);
        Route::get('/informasi', [InformationController::class, 'index']);
        Route::get('/informasi/{id}', [InformationController::class, 'show']);
        Route::get('/laporan-absensi-tahun/{tahun_id}', [LaporanAbsensiStudentController::class, 'tahun']);
        Route::get('/jadwal', [JadwalController::class, 'index']);
        // Tambahkan route lainnya yang spesifik untuk student di sini
    });


    //route untuk wali murid
    Route::get('/logout', [DashboardController::class, 'logout']);
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/tahfidz', [TahfidzController::class, 'index']);;
    Route::get('/izin', [PengajuanIzinController::class, 'index']);
    Route::post('/izin-keluar', [PengajuanIzinController::class, 'store']);
    Route::get('/izin-pulang', [PengajuanPulangController::class, 'index']);
    Route::post('/izin-pulang', [PengajuanPulangController::class, 'store']);
    Route::get('/konseling/{period?}', [KonselingController::class, 'index']);
    Route::get('/period', [PeriodController::class, 'index']);
    Route::get('/lesson', [LessonController::class, 'index']);
    Route::get('/month', [MonthController::class, 'index']);
    Route::get('/semester', [SemesterController::class, 'index']);
    Route::get('/presensi-harian', [PresensiHarianController::class, 'index']);
    Route::get('/presensi-pelajaran', [PresensiPelajaranController::class, 'index']);
    Route::post('/prove', [ProveController::class, 'store']);
    Route::get('/prove', [ProveController::class, 'index']);
    Route::get('/prove/{id}', [ProveController::class, 'detail']);
    Route::patch('/prove/{id}', [ProveController::class, 'update']);
    Route::delete('/prove/{id}', [ProveController::class, 'delete']);
    Route::get('/kesehatan', [KesehatanController::class, 'index']);
    Route::get('/information', [InformationController::class, 'index']);
    Route::get('/information/{id}', [InformationController::class, 'show']);
    Route::get('/tabungan', [TabunganController::class, 'index']);
    Route::post('/tabungan', [TabunganController::class, 'processPayment']);
    Route::get('/kunjungan', [KunjunganController::class, 'index']);
    Route::get('/hubungi-kami', [HubunganKamiController::class, 'index']);

    Route::prefix('v2')->group(function () {
        Route::get('/pembayaran-bulanan', [PembayaranBulananController::class, 'indexV2']);
        Route::get('/riwayat-transaksi-detail/{id}', [RiwayatTransaksiController::class, 'detailV2']);
    });
    //pembayaran bulanan
    Route::get('/pembayaran-bulanan', [PembayaranBulananController::class, 'index']);
    Route::get('/pembayaran-bulanan-lunas', [PembayaranBulananController::class, 'lunas']);
    Route::patch('/pembayaran-bulanan/{id}', [PembayaranBulananCOntroller::class, 'update']);

    //pembayaran bebas
    Route::get('/pembayaran-bebas', [PembayaranBebasController::class, 'index']);
    Route::get('/pembayaran-bebas-lunas', [PembayaranBebasController::class, 'lunas']);
    Route::get('/cicilan-bebas', [PembayaranBebasController::class, 'cicilan']);
    Route::post('/pembayaran-bebas', [PembayaranBebasController::class, 'store']);
    Route::patch('/pembayaran-bebas/{id}', [PembayaranBebasController::class, 'update']);
    Route::delete('/pembayaran-bebas/{id}', [PembayaranBebasController::class, 'destroy']);

    //pembayaran paket
    Route::get('/paket', [PaketController::class, 'index']);
    Route::patch('/paket', [PaketController::class, 'update']);
    Route::get('/paket-lunas', [PaketController::class, 'lunas']);

    //donasi
    Route::get('/donasi', [DonasiController::class, 'index']);
    Route::get('/donasi/{id}', [DonasiController::class, 'show']);
    Route::post('/donasi', [DonasiController::class, 'store']);
    Route::post('/pembayaran-donasi', [DonasiController::class, 'processPayment']);
    Route::get('/daftar-donatur', [DonasiController::class, 'daftarDonasi']);
    Route::get('/pendayagunaan/{program_id}', [PendayagunaanController::class, 'index']);


    //riwayat transaksi tabungan
    Route::get('/riwayat-transaksi-tabungan/{start_date?}/{end_date?}', [TabunganController::class, 'riwayatTransaksi']);
    Route::get('/riwayat-transaksi-tabungan-detail/{id}', [TabunganController::class, 'detailRiwayat']);

    //riwayat transaksi donasi
    Route::get('/riwayat-transaksi-donasi/{start_date?}/{end_date?}', [DonasiController::class, 'riwayatTransaksi']);
    Route::get('/riwayat-transaksi-donasi-detail/{id}', [DonasiController::class, 'detailRiwayat']);


    //ringkasan pembayaran
    Route::get('/ringkasan-pembayaran', [RingkasanPemabayaranController::class, 'index']);

    // Route::get('/flip/callback', [FlipPaymentController::class, 'paymentCallback'])->withoutMiddleware('auth:api');
    Route::get('/flip-channel', [FlipChannelController::class, 'index']);


    Route::get('/ringkasan-pembayaran-paket', [PaketController::class, 'ringkasan']);
    Route::post('/pembayaran', [FlipPaymentController::class, 'processPayment']);

    //flip transaksi
    //Route::get('/riwayat-transaksi', [RiwayatTransaksiController::class, 'index']);
    Route::get('/riwayat-transaksi/{start_date?}/{end_date?}', [RiwayatTransaksiController::class, 'index']);
    Route::get('/riwayat-transaksi-detail/{id}', [RiwayatTransaksiController::class, 'detail']);

    //pembatalan pembayaran
    Route::patch('/pembatalan-pembayaran/{id}', [RiwayatTransaksiController::class, 'pembatalan']);

    //cara pembayaran
    Route::get('/cara-pembayaran', [CaraPembayaranController::class, 'index']);
    Route::get('/notifikasi', [NotifikasiController::class, 'index']);

    //jadwal mengajar
    Route::get('/jadwal', [JadwalController::class, 'index']);
});

//Route::post('/flip/callback', [FlipPaymentController::class, 'paymentCallback']);
//Route::match(['get', 'post'], '/flip/callback', [FlipPaymentController::class, 'paymentCallback']);
Route::match(['get', 'post'], '/flip/callback', [FlipCallbackController::class, 'paymentCallback']);
Route::match(['get', 'post'], '/ipaymu/callback', [IpaymuCallbackController::class, 'ipaymuCallback']);
Route::match(['get', 'post'], '/bni/callback', [BniCallbackController::class, 'bniCallback']);
// Route::get('/get-db', [FlipPaymentController::class, 'coba']);







Route::post('/data-kelas', [KelasController::class, 'index']);
Route::post('/data-user', [GetUserController::class, 'index']);
Route::get('/majors', [MajorController::class, 'index']);
//Route::get('/month', [MonthController::class, 'index']);
Route::get('/day', [DayController::class, 'index']);
Route::get('/laporan-absensi-bulan/{bulan_id?}', [LaporanController::class, 'index']);
Route::get('/laporan-absensi-tahun/{tahun_id}', [LaporanController::class, 'tahun']);
Route::get('/area-absensi', [AreaAbsensiController::class, 'index']);
Route::post('/absen', [DataAbsenController::class, 'store']);
Route::post('/izin', [DataAbsenController::class, 'storeIzin']);
Route::post('/pulang', [DataAbsenController::class, 'storePulang']);
Route::post('/tahfidz', [TahfidzController::class, 'store']);
Route::get('/list-unit', [StudentController::class, 'index']);
Route::get('/list-student/{class_id}', [StudentController::class, 'StudentKelas']);
Route::get('/detail-student/{student_id}', [StudentController::class, 'detailStudent']);
Route::get('/list-laporan-tahfidz/{student_id}', [StudentController::class, 'laporan']);
Route::delete('/laporan-tahfidz/{tahfidz_id}', [StudentController::class, 'delete']);
Route::patch('/laporan-tahfidz/{tahfidz_id}', [StudentController::class, 'update']);
Route::post('/presensi-pelajaran', [PresensiPelajaranController::class, 'store']);
Route::get('/presensi-pelajaran/{presensi_pelajaran_month_id}/{presesnsi_pelajaran_class_id?}', [PresensiPelajaranController::class, 'index']);


Route::get('/sekolah', [SekolahController::class, 'index']);
Route::get('/laporan', [DataAbsenController::class, 'index']);


// employee
// Route::get('/employee', [EmployeeController::class, 'index']);
// Route::get('/employee/{employee_id}', [EmployeeController::class, 'show']);
// Route::post('/employee', [EmployeeController::class, 'store']);
// Route::delete('/employee/{employee_id}', [EmployeeController::class, 'destroy']);
// Route::patch('/employee/{employee_id}', [EmployeeController::class, 'update']);

// holiday
Route::get('/holiday', [HolidayController::class, 'index']);
Route::get('/holiday/{id}', [HolidayController::class, 'show']);
Route::post('/holiday', [HolidayController::class, 'store']);
Route::delete('/holiday/{id}', [HolidayController::class, 'destroy']);
Route::patch('/holiday/{id}', [HolidayController::class, 'update']);

//majors
// Route::get('/majors', [MajorController::class, 'index']);
Route::get('/majors/{majors_id}', [MajorController::class, 'show']);
//Route::post('/majors', [MajorController::class, 'store']);
Route::delete('/majors/{majors_id}', [MajorController::class, 'destroy']);
Route::patch('/majors/{majors_id}', [MajorController::class, 'update']);

//positions
Route::get('/position', [PositionController::class, 'index']);
Route::get('/position/{position_id}', [PositionController::class, 'show']);
Route::post('/position', [PositionController::class, 'store']);
Route::delete('/position/{position_id}', [PositionController::class, 'destroy']);
Route::patch('/position/{position_id}', [PositionController::class, 'update']);

//day
//Route::get('/day', [DayController::class, 'index']);
Route::get('/day/{id}', [DayController::class, 'show']);
Route::post('/day', [DayController::class, 'store']);
Route::delete('/day/{id}', [DayController::class, 'destroy']);
Route::patch('/day/{id}', [DayController::class, 'update']);

//data waktu
Route::get('/data-waktu', [DataWaktuController::class, 'index']);
Route::get('/data-waktu/{data_waktu_id}', [DataWaktuController::class, 'show']);
Route::post('/data-waktu', [DataWaktuController::class, 'store']);
Route::delete('/data-waktu/{data_waktu_id}', [DataWaktuController::class, 'destroy']);
Route::patch('/data-waktu/{data_waktu_id}', [DataWaktuController::class, 'update']);

//period
Route::get('/period', [PeriodController::class, 'index']);
Route::get('/period/{period_id}', [PeriodController::class, 'show']);
Route::post('/period', [PeriodController::class, 'store']);
Route::delete('/period/{period_id}', [PeriodController::class, 'destroy']);
Route::patch('/period/{period_id}', [PeriodController::class, 'update']);

//month
//Route::get('/month', [MonthController::class, 'index']);
Route::get('/month/{month_id}', [MonthController::class, 'show']);
Route::post('/month', [MonthController::class, 'store']);
Route::delete('/month/{month_id}', [MonthController::class, 'destroy']);
Route::patch('/month/{month_id}', [MonthController::class, 'update']);

//presensi
Route::get('/presensi', [PresensiAreaVController::class, 'index']);
Route::get('/presensi/{id_pegawai}', [PresensiAreaVController::class, 'show']);
Route::post('/presensi', [PresensiAreaVController::class, 'store']);
Route::delete('/presensi', [PresensiAreaVController::class, 'destroy']);
Route::patch('/presesnsi/{id_pegawai}', [PresensiAreaVController::class, 'update']);

//data hari libur
