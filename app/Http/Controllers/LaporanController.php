<?php

namespace App\Http\Controllers;

use id;
use Carbon\Carbon;
use App\Models\Employee;
use App\Models\DataAbsen;
use Illuminate\Http\Request;
use App\Services\DatabaseSwitcher;
use Illuminate\Support\Facades\DB;

use function Laravel\Prompts\table;

class LaporanController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/laporan-absensi/{bulan_id}",
     *     summary="Get attendance data",
     *     description="Mengambil data absensi untuk pegawai tertentu berdasarkan bulan",
     *     tags={"Absensi"},
     *     security={{"BearerAuth": {}}},
     *     @OA\Parameter(
     *         name="bulan_id",
     *         in="path",
     *         required=true,
     *         description="ID bulan dalam format YYYY-MM untuk mengambil data absensi bulanan",
     *         @OA\Schema(type="string", example="2024-10")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Data absensi berhasil diambil",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="success"),
     *             @OA\Property(property="hadir", type="integer", description="Jumlah kehadiran"),
     *             @OA\Property(property="izin", type="integer", description="Jumlah izin"),
     *             @OA\Property(property="sakit", type="integer", description="Jumlah sakit"),
     *             @OA\Property(property="terlambat", type="integer", description="Jumlah keterlambatan"),
     *             @OA\Property(property="percentase", type="string", description="Persentase kehadiran bulanan", example="85.00%"),
     *             @OA\Property(property="percentase_hari", type="string", description="Jumlah hari berlalu dalam bulan ini", example="25/31"),
     *             @OA\Property(property="presensi_tahun_ini", type="integer", description="Jumlah presensi tahunan"),
     *             @OA\Property(
     *                 property="rekap",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="hari", type="string", description="Hari dalam format tanggal lokal", example="Senin, 23 Oktober 2024"),
     *                     @OA\Property(property="status_hadir", type="string", description="Status kehadiran", example="Hadir"),
     *                     @OA\Property(
     *                         property="detail",
     *                         type="object",
     *                         @OA\Property(property="jam_datang", type="string", example="08:00:00"),
     *                         @OA\Property(property="jam_pulang", type="string", example="16:00:00"),
     *                         @OA\Property(property="keterangan_datang", type="string", example="Tepat Waktu"),
     *                         @OA\Property(property="keterangan_pulang", type="string", example="Tepat Waktu"),
     *                         @OA\Property(property="area_datang", type="string", example="Kantor Utama"),
     *                         @OA\Property(property="area_pulang", type="string", example="Kantor Utama"),
     *                         @OA\Property(property="catatan_datang", type="string", example="On time"),
     *                         @OA\Property(property="catatan_pulang", type="string", example="On time")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad Request",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Parameter bulan_id diperlukan")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     )
     * )
     */
    // protected $databaseSwitcher;
    // public function __construct(DatabaseSwitcher $databaseSwitcher)
    // {
    //     $this->databaseSwitcher = $databaseSwitcher;
    // }
    public function index($bulan_id)
    {
        // $this->databaseSwitcher->switchDatabaseFromToken(new DataAbsen());
        $user = auth()->user();

        // Cek apakah bulan_id diberikan
        if (!$bulan_id) {
            $data = DB::table('data_absensi')->where('id_pegawai', $user->employee_id)->get();
            return response()->json(['data' => $data]);
        }

        // Ambil data absensi berdasarkan bulan dan pegawai
        $data = DB::table('data_absensi')
            ->where('id_pegawai', $user->employee_id)
            ->where('bulan', $bulan_id)
            ->get();

        // Menghitung status kehadiran
        $statusHadir = $data->filter(function ($item) {
            return !is_null($item->statushadir) && $item->statushadir !== '' && $item->jenis_absen === 'Datang';
        })->count();

        $izin = $data->where('jenis_absen', 'IJIN')->count();
        $izin = $data->filter(function ($item) {
            return strtolower($item->jenis_absen) == 'ijin';
        })->count();
        $terlambat = $data->where('statushadir', 'terlambat')->count();
        $sakit = $data->filter(function ($item) {
            return strtolower($item->jenis_absen) === 'sakit';
        })->count();

        // Ambil data absensi dengan detail
        $absensiData = DB::table('data_absensi')
            ->select(
                'id_pegawai',
                'tanggal',
                DB::raw("GROUP_CONCAT(jenis_absen) as jenis_absen"),
                DB::raw("MAX(CASE WHEN jenis_absen = 'Datang' THEN lokasi END) as area_datang"),
                DB::raw("TIME(MAX(CASE WHEN jenis_absen = 'Datang' THEN jam END)) as jam_datang"),
                DB::raw("MAX(CASE WHEN jenis_absen = 'Datang' THEN catatan_absen END) as catatan_datang"),
                DB::raw("MAX(CASE WHEN jenis_absen = 'Pulang' THEN lokasi END) as area_pulang"),
                DB::raw("TIME(MAX(CASE WHEN jenis_absen = 'Pulang' THEN jam END)) as jam_pulang"),
                DB::raw("MAX(CASE WHEN jenis_absen = 'Pulang' THEN catatan_absen END) as catatan_pulang")
            )
            ->where('id_pegawai', $user->employee_id)
            ->whereIn('jenis_absen', ['Datang', 'Pulang', 'Sakit', 'IJIN', 'Lain-lain'])
            ->where('bulan', $bulan_id)
            ->groupBy('id_pegawai', 'tanggal')
            ->get();
        //dd($absensiData);

        $formattedData = $absensiData->map(function ($item) {
            // Format tanggal menggunakan Carbon
            $formattedDate = Carbon::parse($item->tanggal)->locale('id')->isoFormat('dddd, D MMMM YYYY');

            // Menentukan status hadir
            $statusHadir = 'Tidak Hadir'; // Nilai default

            // Prioritaskan status hadir berdasarkan jenis absen
            if (strpos(strtolower($item->jenis_absen), 'sakit') !== false) {
                $statusHadir = 'Sakit';
            } elseif (strpos(strtolower($item->jenis_absen), 'ijin') !== false) {
                $statusHadir = 'Ijin';
            } elseif (strpos($item->jenis_absen, 'Datang') !== false || strpos($item->jenis_absen, 'Pulang') !== false) {
                $statusHadir = 'Hadir';
            }

            // Logika untuk status datang dan pulang
            $jamMasuk = $item->jam_datang; // Jam masuk
            $statusDatang = is_null($jamMasuk) ? 'Tidak Hadir' : ($jamMasuk <= '08:00:00' ? 'Tepat Waktu' : 'Terlambat');

            $jamKeluar = '16:00:00'; // Jam pulang yang ditentukan
            $jamPulang = $item->jam_pulang; // Jam pulang
            $statusPulang = is_null($jamPulang) ? 'Tidak Pulang' : ($jamPulang >= $jamKeluar ? 'Tepat Waktu' : 'Terlambat');

            return [
                'hari' => $formattedDate,
                'status_hadir' => $statusHadir,
                'detail' => [
                    'jam_datang' => $jamMasuk,
                    'jam_pulang' => $jamPulang,
                    'keterangan_datang' => $statusDatang,
                    'keterangan_pulang' => $statusPulang,
                    'area_datang' => $item->area_datang,
                    'area_pulang' => $item->area_pulang,
                    'catatan_datang' => $item->catatan_datang,
                    'catatan_pulang' => $item->catatan_pulang,
                ]
            ];
        });

        // Hitung jumlah hari dalam bulan yang diberikan
        $jumlahHari = Carbon::createFromFormat('Y-m', $bulan_id)->daysInMonth;
        $hariSekarang = Carbon::now()->day;
        $persentaseHari = "{$hariSekarang}/{$jumlahHari}";

        // Hitung persentase kehadiran per hari
        $persentaseKehadiran = $jumlahHari > 0 ? ($statusHadir / $jumlahHari) * 100 : 0;

        // Hitung jumlah presensi tahun ini
        $tahun = Carbon::parse($bulan_id)->year;
        $jumlahPresensiTahunIni = DB::table('data_absensi')->where('id_pegawai', $user->employee_id)
            ->where('jenis_absen', 'Datang')
            ->whereYear('tanggal', $tahun)
            ->count();

        return response()->json([
            'is_correct' => true,
            'message' => 'success',
            'hadir' => $statusHadir,
            'izin' => $izin,
            'sakit' => $sakit,
            'terlambat' => $terlambat,
            'percentase' => number_format($persentaseKehadiran, 2) . '%',
            'percentase_hari' => $persentaseHari,
            'presensi_tahun_ini' => $jumlahPresensiTahunIni,
            'rekap' => $formattedData,
        ]);
    }

    public function tahun($tahun)
    {
        $user = auth()->user();

        $data = DataAbsen::where('id_pegawai', $user->employee_id)->get();
        if ($data) {
            $countDatang = $data->where('jenis_absen', 'Datang')->where('statushadir', 'tepat waktu')->count();
            $countIzin = $data->where('jenis_absen', 'IJIN')->count();
            $countSakit = $data->where('jenis_absen', 'SAKIT')->count();
            $countLain = $data->where('jenis_absen', 'Lain-lain')->count();
            $countTerlambat = $data->where('jenis_absen', 'Datang')->where('statushadir', 'terlambat')->count();
            // dd($countTerlambat);
            $total = $countDatang + $countTerlambat;
            $maxValue = 366; // Total hari atau nilai maksimum untuk perhitungan

            $persentasi = ceil(($total / $maxValue) * 100 * 100) / 100; // Hitung persentase dan bulatkan ke atas
            //   dd($persentasi);
            return response()->json([
                'is_correct' => true,
                'message' => 'success',
                'total' => $total,
                'hari pertahun' => $maxValue,
                'persentasi' => $persentasi,
                'hadir' => $countDatang,
                'izin' => $countIzin,
                'sakit' => $countSakit,
                'lain' => $countLain,
                'terlambat' => $countTerlambat
            ], 200);
        } else {
            return response()->json([
                'is_correct' => false,
                'message' => 'data belum tersedia'
            ]);
        }
    }
}
