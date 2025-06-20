<?php

namespace App\Http\Controllers;

use App\Models\day;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JadwalController extends Controller
{

    public function index(Request $request)
    {
        try {
            $user = auth()->user();
            $dayId = $request->input('hari');
            $hari = day::where('day_name', $dayId)->first();
            $getDayId = $hari->day_id;
            // $getDayId = 4;
            $schedules = DB::table('schedule')
                ->select([
                    'schedule.schedule_class_id',
                    'schedule.schedule_lesson_id',
                    'class.class_name as kelas',
                    'lesson.lesson_name as subject',
                    // 'lesson.sks',
                    DB::raw("CONCAT(jam_pelajaran.jam_pelajaran_start, ' - ', jam_pelajaran.jam_pelajaran_end) as jam"),
                    'day.day_id'
                ])
                ->join('class', 'schedule.schedule_class_id', '=', 'class.class_id')
                ->join('lesson', 'schedule.schedule_lesson_id', '=', 'lesson.lesson_id')
                ->join('employee', 'lesson.lesson_teacher', '=', 'employee.employee_id')
                ->join('jam_pelajaran', 'schedule.schedule_jampel', '=', 'jam_pelajaran.jam_pelajaran_id')
                ->join('day', 'schedule.schedule_day', '=', 'day.day_id')
                ->where('schedule.schedule_class_id', $user->class_class_id)
                ->where('day.day_id', $getDayId) // Assuming you have a $dayId variable
                ->get();

            // Format data sesuai struktur yang diinginkan
            $formattedData = [
                'is_correct' => true,
                'jadwal' => []
            ];

            // Kelompokkan data berdasarkan kelas
            $groupedByClass = $schedules->groupBy('kelas');

            $formattedData = [
                'is_correct' => true,
                'jadwal' => $schedules->map(function ($item) {
                    $totalPertemuan = 28;
                    $totalMasuk = 14; // Ganti dengan logika perhitungan yang sesuai
                    $persentaseAbsen = $totalMasuk / $totalPertemuan * 100; //ini dalam persen
                    $persentaseDesimal = $totalMasuk / $totalPertemuan; // ini dalam desimal

                    // Pisahkan jam mulai dan selesai
                    $jamParts = explode(' - ', $item->jam);

                    return [
                        'subject' => $item->subject,
                        // 'sks' => $item->sks,
                        'lesson_id' => $item->schedule_lesson_id,
                        'jam_mulai' => substr($jamParts[0], 0, 5),
                        'jam_selesai' => substr($jamParts[1], 0, 5),
                        // "presentase_absen" => round($persentaseAbsen) . "%",
                        // "persentase_absen_nomor" => $persentaseDesimal,
                    ];
                })->toArray()
            ];


            return response()->json($formattedData);
        } catch (\Throwable $th) {
            return response()->json([
                'is_corect' => false,
                'message' => $th->getMessage(),

            ], 401);
        }
    }
}
