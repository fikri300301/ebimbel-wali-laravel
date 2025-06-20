<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PresensiHarian;
use Illuminate\Support\Facades\DB;

class PresensiHarianController extends Controller
{
    public function index2(Request $request)
    {
        $user = auth()->user();
        $query = PresensiHarian::where('presensi_harian_student_id', $user->student_id);

        $period = $request->query('period');
        $bulan = $request->query('bulan');
        // Jika parameter bulan tidak null, tambahkan filter bulan
        if ($period) {
            $query->where('presensi_harian_period_id', $period);
        }

        if ($bulan) {
            $query->where('presensi_harian_month_id', $bulan);
        }

        // Ambil data dan format hasilnya
        $data = $query->get()->map(function ($item) {
            return [
                'presensi_harian_id' => $item->presensi_harian_id,
                'presensi_harian_date' => $item->presensi_harian_date,
                'presensi_harian_status' => $item->presensi_harian_status
            ];
        });

        // Periksa jika data ditemukan

        return response()->json([
            'is_correct' => true,
            'message' => 'success',
            'presensi' => $data
        ]);
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        $query = PresensiHarian::where('presensi_harian_student_id', $user->student_id);
        $bulan = $request->query('bulan');
        // dd($bulan);
        // For presensi_harian table

        $bulanSekolah = [
            1 => 'juli',
            2 => 'agustus',
            3 => 'september',
            4 => 'oktober',
            5 => 'november',
            6 => 'desember',
            7 => 'januari',
            8 => 'februari',
            9 => 'maret',
            10 => 'april',
            11 => 'mei',
            12 => 'juni'
        ];
        $bulanEnglish = [
            'januari' => 'January',
            'februari' => 'February',
            'maret' => 'March',
            'april' => 'April',
            'mei' => 'May',
            'juni' => 'June',
            'juli' => 'July',
            'agustus' => 'August',
            'september' => 'September',
            'oktober' => 'October',
            'november' => 'November',
            'desember' => 'December'
        ];

        $tahun = date('Y');

        // Get the Indonesian month name
        $bulanName = $bulanSekolah[$bulan];

        // Convert to English month name for strtotime()
        $bulanEnglishName = $bulanEnglish[$bulanName];

        // Get first day of the month
        $tanggalawal = date('Y-m-d', strtotime("first day of $bulanEnglishName $tahun"));

        // Get last day of the month
        $tanggalakhir = date('Y-m-d', strtotime("last day of $bulanEnglishName $tahun"));

        //  dd($tanggalawal, $tanggalakhir);

        //ambil $bulan dari request lalu ambil tanggal awal sampai akhir nya


        $firstQuery = DB::table('presensi_harian as ph')
            ->select(
                'ph.presensi_harian_id as presesnsi_harian_id',
                #'ph.presensi_harian_student_id as student_presensi',
                'ph.presensi_harian_date as presensi_harian_date',
                'ph.presensi_harian_status as presensi_harian_status'
                # DB::raw("'Hadir' as presensi_harian_status")
                //     DB::raw("CASE
                //     WHEN ph.presensi_harian_status = 'H' THEN 'Hadir'
                //     WHEN ph.presensi_harian_status = 'S' THEN 'S'
                //     ELSE ph.presensi_harian_status
                //   END as status_presensi")
            )
            ->where('ph.presensi_harian_student_id', $user->student_id)
            //->where()
            ->whereBetween('ph.presensi_harian_date', [$tanggalawal, $tanggalakhir]);

        // For presensi_student table
        $secondQuery = DB::table('presensi_student as ps')
            ->select(
                'ps.id as presesnsi_harian_id',
                #'ps.id_student as student_presensi',
                'ps.date as presensi_harian_date',
                DB::raw("CASE WHEN ps.jenis_absen = 'DATANG' THEN 'H' WHEN ps.jenis_absen = 'IZIN' THEN 'I'  WHEN ps.jenis_absen = 'SAKIT' THEN 'S' ELSE ps.jenis_absen END as presensi_harian_status")
            )
            ->where('ps.id_student', $user->student_id)
            ->where('jenis_absen', '!=', 'PULANG')
            ->whereBetween('ps.date', [$tanggalawal, $tanggalakhir]);

        // Combine both queries with unionAll
        $data = $firstQuery->unionAll($secondQuery)->get();

        // Periksa jika data ditemukan

        return response()->json([
            'is_correct' => true,
            'message' => 'success',
            'presensi' => $data
        ]);
    }
}
