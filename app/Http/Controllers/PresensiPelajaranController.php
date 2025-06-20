<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\teaching;
use App\Models\PresesnsiPelajaran;
use App\Models\Semester;
use App\Services\DatabaseSwitcher;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;

class PresensiPelajaranController extends Controller
{
    // protected $databaseSwitcher;

    // // Inject DatabaseSwitcher di constructor
    // public function __construct(DatabaseSwitcher $databaseSwitcher)
    // {
    //     $this->databaseSwitcher = $databaseSwitcher;
    // }
    /**
     * @OA\Get(
     *     path="/api/presensi-pelajaran/{presensi_pelajaran_month_id}/{presensi_pelajaran_class_id}",
     *     summary="Get data presensi pelajaran",
     *     description="List presensi pelajaran berdasarkan bulan, dan opsional berdasarkan kelas.",
     *     tags={"Presensi Pelajaran"},
     *     security={{"BearerAuth": {}}},
     *     @OA\Parameter(
     *         name="presensi_pelajaran_month_id",
     *         in="path",
     *         required=true,
     *         description="ID bulan untuk mengambil detail data",
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="presensi_pelajaran_class_id",
     *         in="path",
     *         required=false,
     *         description="ID kelas untuk mengambil detail data (opsional)",
     *         @OA\Schema(
     *             type="integer",
     *             default=null
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Data detail presensi pelajaran",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="success"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="tanggal", type="string", example="14 October 2024", description="Tanggal presensi"),
     *                     @OA\Property(property="waktu", type="string", example="14:30:00", description="Waktu presensi"),
     *                     @OA\Property(property="lesson", type="string", example="PPKN", description="Nama pelajaran"),
     *                     @OA\Property(property="class", type="string", example="rofi", description="Nama kelas"),
     *                     @OA\Property(property="jumlahHadir", type="integer", example=1, description="Jumlah siswa hadir"),
     *                     @OA\Property(property="jumlahSakit", type="integer", example=2, description="Jumlah siswa sakit"),
     *                     @OA\Property(property="jumlahIjin", type="integer", example=3, description="Jumlah siswa izin"),
     *                     @OA\Property(property="jumlahAlfa", type="integer", example=4, description="Jumlah siswa alfa"),
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Data tidak ditemukan",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Data tidak ditemukan")
     *         )
     *     )
     * )
     */

    public function index(Request $request)
    {
        $user = auth()->user();
        //  dd($user);
        // Ambil semester_id dan period_id dari request
        $semesterId = $request->query('semester_id');

        $periodId = $request->query('period_id');

        // Jika semester_id dan period_id tidak diberikan, ambil semua data
        if (!$semesterId || !$periodId) {
            $query = PresesnsiPelajaran::where('presensi_pelajaran_student_id', $user->student_id);

            $semester = $request->query('semester');
            $period = $request->query('period');
            $bulan = $request->query('bulan');
            $lesson = $request->query('lesson');

            // Filter berdasarkan semester jika ada
            if ($semester) {
                $dataSemester = Semester::where('semester_id', $semester)->first();

                if ($dataSemester) {
                    if ($dataSemester->semester_name == 'Semester 1') {
                        // Jika semester 1, filter bulan 1-6
                        if ($bulan) {
                            $query->where('presensi_pelajaran_month_id', $bulan)
                                ->whereBetween('presensi_pelajaran_month_id', [1, 6]);
                        } else {
                            $query->whereBetween('presensi_pelajaran_month_id', [1, 6])
                                ->get();
                            // dd($query);
                        }
                    } elseif ($dataSemester->semester_name == 'Semester 2') {
                        // Jika semester 2, filter bulan 7-12
                        if ($bulan) {
                            $query->where('presensi_pelajaran_month_id', $bulan)
                                ->whereBetween('presensi_pelajaran_month_id', [7, 12]);
                        } else {
                            $query->whereBetween('presensi_pelajaran_month_id', [7, 12]);
                        }
                    }
                }
            } elseif ($bulan) {
                // Jika tidak ada filter semester, gunakan filter bulan biasa
                $query->where('presensi_pelajaran_month_id', $bulan);
            }

            // Filter berdasarkan period dan lesson jika ada
            if ($period) {
                $query->where('presensi_pelajaran_period_id', $period);
            }

            if ($lesson) {
                $query->where('presensi_pelajaran_lesson_id', $lesson);
            }
            //    dd($query);
            // Ambil data presensi sesuai filter yang diterapkan
            $data = $query->get()->map(function ($item) {
                return [
                    'presensi_pelajaran_id' => $item->presensi_pelajaran_id,
                    'presensi_pelajaran_date' => $item->presensi_pelajaran_date,
                    'presensi_pelajaran_period_id' => $item->presensi_pelajaran_period_id,
                    'presensi_pelajaran_month_id' => $item->presensi_pelajaran_month_id,
                    'presensi_pelajaran_status' => $item->presensi_pelajaran_status,
                    'presensi_pelajaran_lesson_id' => $item->presensi_pelajaran_lesson_id,
                ];
            });
            //  dd($data);
            return response()->json([
                'is_correct' => true,
                'message' => 'success',
                'presensi_pelajaran' => $data
            ]);
        }

        // Jika semester_id dan period_id diberikan, lakukan validasi
        $semester = Semester::where('semester_id', $semesterId)
            ->where('semester_period_id', $periodId)
            ->first();

        // Cek apakah semester dan period valid
        if (!$semester) {
            return response()->json([
                'is_correct' => false,
                'message' => 'Semester atau Period tidak valid.',
            ], 400); // Kode status 400 berarti bad request
        }

        // Jika semester valid, lanjutkan ke pencarian presensi
        $query = PresesnsiPelajaran::where('presensi_pelajaran_student_id', $user->student_id);

        $period = $request->query('period');
        $bulan = $request->query('bulan');
        $lesson = $request->query('lesson');

        // Filter berdasarkan period, bulan, dan lesson jika ada
        if ($period) {
            $query->where('presensi_pelajaran_period_id', $period);
        }

        if ($bulan) {
            $query->where('presensi_pelajaran_month_id', $bulan);
        }

        if ($lesson) {
            $query->where('presensi_pelajaran_lesson_id', $lesson);
        }

        // Ambil data presensi sesuai filter yang diterapkan
        $data = $query->get()->map(function ($item) {
            return [
                'presensi_pelajaran_id' => $item->presensi_pelajaran_id,
                'presensi_pelajaran_date' => $item->presensi_pelajaran_date,
                'presensi_pelajaran_period_id' => $item->presensi_pelajaran_period_id,
                'presensi_pelajaran_month_id' => $item->presensi_pelajaran_month_id,
                'presensi_pelajaran_status' => $item->presensi_pelajaran_status,
                'presensi_pelajaran_lesson_id' => $item->presensi_pelajaran_lesson_id,
            ];
        });

        return response()->json([
            'is_correct' => true,
            'message' => 'success',

            'data' => $data
        ]);
    }



    public function index1($presensi_pelajaran_month_id, $presensi_pelajaran_class_id = null)
    {
        $user = auth()->user();
        $employee = $user->employee_id;

        // Ambil semua teaching_id untuk employee_id
        $teachingIds = DB::table('teaching')
            ->where('teaching_employee_id', $employee)
            ->pluck('teaching_id'); // Mengembalikan array teaching_id

        // Jika tidak ada teaching_id yang ditemukan
        if ($teachingIds->isEmpty()) {
            return response()->json([
                'is_correct' => false,
                'message' => 'Data tidak ditemukan',
                'data' => []
            ], 200);
        }

        // Query presensi_pelajaran
        $query = DB::table('presensi_pelajaran')
            ->join('lesson', 'presensi_pelajaran.presensi_pelajaran_lesson_id', '=', 'lesson.lesson_id')
            ->join('class', 'presensi_pelajaran.presensi_pelajaran_class_id', '=', 'class.class_id')
            ->select(
                DB::raw('DATE(presensi_pelajaran.presensi_pelajaran_date) as tanggal'),
                DB::raw('TIME(presensi_pelajaran.presensi_pelajaran_created_date) as jam_dibuat'),
                'lesson.lesson_name as lesson',
                'class.class_name as class',
                DB::raw('SUM(CASE WHEN presensi_pelajaran.presensi_pelajaran_status = "H" THEN 1 ELSE 0 END) as jumlahHadir'),
                DB::raw('SUM(CASE WHEN presensi_pelajaran.presensi_pelajaran_status = "S" THEN 1 ELSE 0 END) as jumlahSakit'),
                DB::raw('SUM(CASE WHEN presensi_pelajaran.presensi_pelajaran_status = "I" THEN 1 ELSE 0 END) as jumlahIjin'),
                DB::raw('SUM(CASE WHEN presensi_pelajaran.presensi_pelajaran_status = "A" THEN 1 ELSE 0 END) as jumlahAlfa')
            )
            ->whereIn('presensi_teaching_id', $teachingIds)
            ->whereMonth('presensi_pelajaran_date', $presensi_pelajaran_month_id);

        if ($presensi_pelajaran_class_id) {
            $query->where('presensi_pelajaran.presensi_pelajaran_class_id', $presensi_pelajaran_class_id);
        }

        // Kelompokkan berdasarkan tanggal, pelajaran, dan kelas
        $query->groupBy('tanggal', 'jam_dibuat', 'lesson.lesson_name', 'class.class_name');

        // Urutkan berdasarkan tanggal dan pelajaran
        $query->orderBy('tanggal')->orderBy('lesson.lesson_name');

        $teachingPresensi = $query->get();
        // dd($teachingPresensi);
        // Jika ada data, format menjadi JSON
        if ($teachingPresensi->isNotEmpty()) {
            $formattedOutput = $teachingPresensi->map(function ($presensi) {
                return [
                    'tanggal' => Carbon::parse($presensi->tanggal)->translatedFormat('d F Y'),
                    'jam' => $presensi->jam_dibuat,
                    'lesson' => $presensi->lesson,
                    'class' => $presensi->class,
                    'jumlahHadir' => (int)$presensi->jumlahHadir,
                    'jumlahSakit' => (int)$presensi->jumlahSakit,
                    'jumlahIjin' => (int)$presensi->jumlahIjin,
                    'jumlahAlfa' => (int)$presensi->jumlahAlfa,
                ];
            });

            return response()->json([
                'is_correct' => true,
                'message' => 'success',
                'data' => $formattedOutput,
            ], 200);
        } else {
            return response()->json([
                'is_correct' => true,
                'message' => 'Presensi belum tersedia.',
                'data' => []
            ], 200);
        }
    }
}
