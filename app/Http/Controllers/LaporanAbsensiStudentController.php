<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\PresensiStudent;
use Illuminate\Support\Facades\DB;

class LaporanAbsensiStudentController extends Controller
{
    private function getBulanFromNama($bulan_nama)
    {
        $bulan_map = [
            'januari' => "01",
            'februari' => "02",
            'maret' => "03",
            'april' => "04",
            'mei' => "05",
            'juni' => "06",
            'juli' => "07",
            'agustus' => "08",
            'september' => "09",
            'oktober' => "10",
            'november' => "11",
            'desember' => "12",
        ];

        // Ubah nama bulan menjadi lowercase dan cari bulan yang sesuai
        $bulan_nama = strtolower($bulan_nama);

        return $bulan_map[$bulan_nama] ?? "01";  // Mengembalikan null jika bulan tidak valid
    }

    public function index(Request $request)
    {
        try {
            $tahun = $request->input('tahun');
            $bulan = $request->input('bulan');

            // $tahun = $tahun . '-' . $bulan;
            // dd($tahun);
            $bulan = $this->getBulanFromNama($bulan);
            $user = auth()->user();
            $student = $user->student_id;

            $hadir = PresensiStudent::where('id_student', $student)
                ->where('jenis_absen', 'DATANG')
                ->whereYear('date', $tahun)
                ->whereMonth('date', $bulan)
                ->count();

            $sakit = PresensiStudent::where('id_student', $student)
                ->where('jenis_absen', 'SAKIT')
                ->whereYear('date', $tahun)
                ->whereMonth('date', $bulan)
                ->count();

            $alpha = PresensiStudent::where('id_student', $student)
                ->where('jenis_absen', 'ALPHA')
                ->whereYear('date', $tahun)
                ->whereMonth('date', $bulan)
                ->count();

            $izin = PresensiStudent::where('id_student', $student)
                ->where('jenis_absen', 'IZIN')
                ->whereYear('date', $tahun)
                ->whereMonth('date', $bulan)
                ->count();

            $terlambat = PresensiStudent::where('id_student', $student)
                ->where('status', 'Terlambat')
                ->whereYear('date', $tahun)
                ->whereMonth('date', $bulan)
                ->count();

            $presensi_tahun_ini = PresensiStudent::where('id_student', $student)
                ->where('jenis_absen', 'DATANG')
                ->whereYear('date', $tahun)
                ->count();
            // $sakit = PresensiStudent::where('id_student', $student)->where('jenis_absen', 'SAKIT')->count();
            // $lain_lain = PresensiStudent::where('id_student', $student)->where('jenis_absen', 'LAIN-LAIN')->count();
            // $izin = PresensiStudent::where('id_student', $student)->where('jenis_absen', 'IZIN')->count();
            // $terlambat = PresensiStudent::where('id_student', $student)->where('status', 'Terlambat')->count();

            $jumlahHariBulan = Carbon::createFromFormat('Y-m', "{$tahun}-{$bulan}")->daysInMonth;
            $jumlahAbsen = $hadir + $sakit + $alpha + $izin;

            $persentase_hari = "{$jumlahAbsen}/{$jumlahHariBulan}";
            $persentaseKehadiran = ($hadir / $jumlahHariBulan) * 100;
            $persentaseKehadiran2 =  ($hadir / $jumlahHariBulan) * 100;
            // dd($persentaseKehadiran);

            $dataRekap =  DB::table('presensi_student as pd')
                ->leftJoin('presensi_student as pp', function ($join) {
                    $join->on('pp.id_student', '=', 'pd.id_student')
                        ->on('pp.date', '=', 'pd.date')
                        ->where('pp.jenis_absen', '=', 'PULANG');
                })
                ->join('area_absensi as aa', 'aa.id_area', '=', 'pd.id_area_absensi')
                ->leftJoin('area_absensi as ap', 'ap.id_area', '=', 'pp.id_area_absensi')
                ->select(
                    'pd.jenis_absen as status_hadir',
                    'pd.date',
                    'pd.time as jam_datang',
                    'pp.time as jam_pulang1',
                    'pp.jenis_absen as jam_pulang',
                    'pd.status as keterangan_datang',
                    'pd.status as keterangan_datang',
                    'aa.nama_area as area_datang',
                    'ap.nama_area as area_pulang',
                    'pd.note as catatan_datang',
                    'pp.note as catatan_pulang',
                    // 'pd.id_student',
                    // 'pd.id_area_absensi',
                    // 'pd.longi',
                    // 'pd.lati',
                    // 'pp.time as waktu_pulang',
                )
                ->where('pd.id_student', $student)
                ->whereYear('pd.date', $tahun)
                ->whereMonth('pd.date', $bulan)
                ->whereIn('pd.jenis_absen', ['DATANG', 'SAKIT', 'ALPHA', 'IZIN'])
                ->get();
            //   dd($dataRekap);
            $rekap = [];
            foreach ($dataRekap as $data) {
                $tanggal = Carbon::parse($data->date)->format('j-M-Y');

                $status = strtolower($data->status_hadir);
                if ($status == 'datang') {
                    $status = 'hadir';
                }

                $rekap[] = [
                    'hari' => $tanggal,
                    'status_hadir' => $status,

                    'detail' => [
                        'jam_datang' => in_array($status, ['izin', 'sakit', 'alpha']) ? null : $data->jam_datang,
                        'jam_pulang' => $data->jam_pulang1,
                        'keterangan_datang' => $data->keterangan_datang,
                        'keterangan_pulang' => ($data->jam_pulang === null) ? null : null,
                        'area_datang' => in_array($status, ['izin', 'sakit', 'alpha']) ? null : $data->jam_datang,
                        'area_pulang' => $data->area_pulang,
                        'catatan_datang' => $data->catatan_datang ?? "",
                        'catatan_pulang' => $data->catatan_pulang
                    ]
                ];
            }

            return response()->json([
                'is_correct' => true,
                'message' => 'success',
                'hadir' => $hadir,
                'sakit' => $sakit,
                'alpha' => $alpha,
                'izin' => $izin,
                'terlambat' => $terlambat,
                'percentase' => number_format($persentaseKehadiran2, 2) . '%',
                'percentase2' => number_format($persentaseKehadiran2, 2) / 100,
                'persentase_hari' => $persentase_hari,
                'presensi_tahun_ini' => $presensi_tahun_ini,
                'rekap' => $rekap
                //
                // 'data' => $presensi
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => 'Gagal mendapatkan data',
                'data' => $th->getMessage()
            ], 500);
        }
    }

    public function tahun($tahun)
    {
        try {
            $user = auth()->user();
            $contDatang = PresensiStudent::where('id_student', $user->student_id)
                ->where('jenis_absen', 'DATANG')
                ->whereYear('date', $tahun)
                ->count();

            $contSakit = PresensiStudent::where('id_student', $user->student_id)
                ->where('jenis_absen', 'SAKIT')
                ->whereYear('date', $tahun)
                ->count();

            $contAlpha = PresensiStudent::where('id_student', $user->student_id)
                ->where('jenis_absen', 'ALPHA')
                ->whereYear('date', $tahun)
                ->count();

            $contIzin = PresensiStudent::where('id_student', $user->student_id)
                ->where('jenis_absen', 'IZIN')
                ->whereYear('date', $tahun)
                ->count();

            $contTerlambat = PresensiStudent::where('id_student', $user->student_id)
                ->where('status', 'Terlambat')
                ->whereYear('date', $tahun)
                ->count();

            $total = $contDatang + $contSakit + $contAlpha + $contIzin;
            $maxValue = 365;

            $persentasi = ($total / $maxValue) * 100;
            $persentasi = round($persentasi, 3);

            return response()->json([
                'is_correct' => true,
                'message' => 'success',
                'total' => $total,
                'hari_pertahun' => $maxValue,
                'persentasi' => $persentasi,
                'hadir' => $contDatang,
                'izin' => $contIzin,
                'sakit' => $contSakit,
                'lain_lain' => $contAlpha,
                'terlambat' => $contTerlambat,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => 'Gagal mendapatkan data',
                'data' => $th->getMessage()
            ], 500);
        }
    }
}
