<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\major;
use App\Models\Student;
use App\Models\Tahfidz;
use Illuminate\Http\Request;
use App\Services\DatabaseSwitcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class StudentController extends Controller
{
    // protected $databaseSwitcher;

    // // Inject DatabaseSwitcher di constructor
    // public function __construct(DatabaseSwitcher $databaseSwitcher)
    // {
    //     $this->databaseSwitcher = $databaseSwitcher;
    // }
    /**
     * @OA\Get(
     *     path="/api/list-unit",
     *     summary="Get data unit",
     *     description="Mengambil semua unit dan kelas nya",
     *     tags={"List Unit class and student"},
     *     security={{"BearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Data list unit",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id_jurusan", type="integer", example=1, description="ID Jurusan"),
     *                     @OA\Property(property="jurusan_name", type="string", example="Teknik Informatika", description="Nama Jurusan"),
     *                     @OA\Property(
     *                         property="data_class",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="class_id", type="integer", example=101, description="ID Kelas"),
     *                             @OA\Property(property="class_name", type="string", example="Kelas A", description="Nama Kelas")
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Not Found",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="error")
     *         )
     *     )
     * )
     */

    public function index()
    {
        // Switch ke database yang sesuai dengan user
        //  $this->databaseSwitcher->switchDatabaseFromToken(new major());

        // Mengambil semua jurusan beserta kelas-kelas terkait menggunakan query builder
        $majors = DB::table('majors')
            ->leftJoin('class', 'majors.majors_id', '=', 'class.majors_majors_id')
            ->select('majors.majors_id as major_id', 'majors.majors_name as major_name', 'class.class_id as class_id', 'class.class_name as class_name')
            ->get();

        // Mengelompokkan data kelas berdasarkan major_id
        $response = $majors->groupBy('major_id')->map(function ($classes, $major_id) {
            $firstClass = $classes->first();
            return [
                'id_jurusan' => $major_id,
                'jurusan_name' => $firstClass->major_name,
                'data_class' => $classes->filter(function ($class) {
                    return $class->class_id !== null; // Menghindari data kelas null jika jurusan tidak memiliki kelas
                })->map(function ($class) {
                    return [
                        'class_id' => $class->class_id,
                        'class_name' => $class->class_name
                    ];
                })->values()
            ];
        })->values();

        // Mengembalikan response dalam format JSON
        return response()->json($response);
    }

    /**
     * @OA\Get(
     *     path="/api/list-student/{class_id}",
     *     summary="Get data list student by class",
     *     description="Mengambil data kelas dan siswa nya",
     *     tags={"List Unit class and student"},
     *     security={{"BearerAuth": {}}},
     *     @OA\Parameter(
     *         name="class_id",
     *         in="path",
     *         required=true,
     *         description="ID kelas untuk mengambil data siswa",
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Data list kelas dan siswa nya",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="studentId", type="integer", example=1, description="ID Siswa"),
     *                     @OA\Property(property="studentName", type="string", example="John Doe", description="Nama Siswa")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Not Found",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="data not found")
     *         )
     *     )
     * )
     */

    public function StudentKelas($class_id)
    {
        //  $Model = $this->databaseSwitcher->switchDatabaseFromToken(new Kelas());

        // Mengambil kelas berdasarkan class_id dan mengaitkan dengan data student
        $kelas = DB::table('class')
            ->leftJoin('student', 'class.class_id', '=', 'student.class_class_id') // Menghubungkan class dengan student
            ->select('class.class_id', 'class.class_name', 'student.student_id', 'student.student_full_name')
            ->where('class.class_id', $class_id)
            ->first();

        if (is_null($kelas)) {
            return response()->json([
                'is_correct' => false,
                'message' => 'data not found'
            ], 400);
        }

        // Mengambil semua student yang terdaftar di kelas ini
        $students = DB::table('student')
            ->where('class_class_id', $class_id)
            ->get();

        $response = [
            'is_correct' => true,
            'message' => 'success',
            'dataStudent' => $students->map(function ($student) {
                return [
                    'studentId' => $student->student_id,
                    'studentName' => $student->student_full_name,
                ];
            })
        ];

        return response()->json($response);
    }

    /**
     * @OA\Get(
     *     path="/api/detail-student/{student_id}",
     *     summary="Get data detail student",
     *     description="detail student",
     *     tags={"List Unit class and student"},
     *     security={{"BearerAuth": {}}},
     *     @OA\Parameter(
     *         name="student_id",
     *         in="path",
     *         required=true,
     *         description="ID student untuk mengambil detail student",
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Data detail student",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="tahun_ajaran", type="string", example=1, description="tahun"),
     *                     @OA\Property(property="studentId", type="integer", example="2", description="ID Siswa"),
     *                     @OA\Property(property="student_nis", type="string", example="2", description="Nis Siswa"),
     *                     @OA\Property(property="studentName", type="string", example="nama siswa", description="Nama Siswa"),
     *                     @OA\Property(property="studentMajor", type="string", example="MI", description="Jurusan Siswa"),
     *                     @OA\Property(property="studentClass", type="string", example="nama keals", description="nama kelas"),
     *                     @OA\Property(property="total_hafalan", type="integer", example="2", description="total hafalan siswa Siswa"),
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Not Found",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="data not found")
     *         )
     *     )
     * )
     */
    public function detailStudent($student_id)
    {
        //$this->databaseSwitcher->switchDatabaseFromToken(new Student());

        // Mengambil data siswa berdasarkan student_id dan mengaitkan dengan jurusan dan kelas
        $student = DB::table('student')
            ->leftJoin('class', 'student.class_class_id', '=', 'class.class_id') // Menghubungkan tabel student dengan class
            ->leftJoin('majors', 'student.majors_majors_id', '=', 'majors.majors_id') // Menghubungkan tabel student dengan majors
            ->select(
                'student.student_id',
                'student.student_nis',
                'student.student_full_name',
                'majors.majors_name',
                'class.class_name'
            )
            ->where('student.student_id', $student_id)
            ->first();

        if (!$student) {
            return response()->json([
                'is_correct' => false,
                'message' => 'Data not found'
            ], 404);
        }

        // Mengambil total hafalan dari tabel tahfidz
        $totalHafalan = DB::table('tahfidz')
            ->where('tahfidz_student_id', $student_id) // Menghubungkan dengan student menggunakan tahfidz_student_id
            ->sum('tahfidz_new'); // Menghitung total hafalan, pastikan untuk mengkonversi jika perlu

        // Membentuk response
        $response = [
            'is_correct' => true,
            'message' => 'success',
            'dataStudent' => [
                'tahun_ajaran' => 'N/A', // Ganti ini jika Anda memiliki logika untuk mengambil tahun ajaran
                'studentId' => $student->student_id,
                'student_nis' => $student->student_nis,
                'studentName' => $student->student_full_name,
                'studentMajor' => $student->majors_name, // Jurusan siswa
                'studentClass' => $student->class_name, // Kelas siswa
                'total_hafalan' => $totalHafalan, // Total hafalan
            ]
        ];

        return response()->json($response);
    }

    /**
     * @OA\Get(
     *     path="/api/list-laporan-tahfidz/{student_id}",
     *     summary="Get data detail student tahfidz",
     *     description="list student tahfidz",
     *     tags={"Tahfidz"},
     *     security={{"BearerAuth": {}}},
     *     @OA\Parameter(
     *         name="student_id",
     *         in="path",
     *         required=true,
     *         description="ID student untuk mengambil detail student",
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Data detail student",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="tahfidz_id", type="integer", example="2", description="tahfidz_id"),
     *                     @OA\Property(property="tanggal", type="string", example="2024-10-14", description="tahun"),
     *                     @OA\Property(property="jumlah_ayat_baru", type="integer", example="2", description="jumlah ayat baru"),
     *                     @OA\Property(property="ket_hafalan_baru", type="string", example="2", description="ket.hafalan baru"),
     *                     @OA\Property(property="murojaah", type="string", example="surat an nas", description="murojaah"),
     *                     @OA\Property(property="murojaah_hafalan_baru", type="string", example="MI", description="hafalan"),
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Not Found",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="data not found")
     *         )
     *     )
     * )
     */

    public function laporan($student_id)
    {
        // $this->databaseSwitcher->switchDatabaseFromToken(new Student());

        // Mengambil data siswa berdasarkan student_id
        $student = DB::table('student')->where('student_id', $student_id)->first();

        if (!$student) {
            return response()->json([
                'is_correct' => false,
                'message' => 'data not found'
            ], 400);
        }
        // dd($student);
        // Mengambil laporan tahfidz berdasarkan student_id
        $laporan = DB::table('tahfidz')
            ->where('tahfidz_student_id', $student_id)
            ->select('tahfidz_id', 'tahfidz_date', 'tahfidz_new', 'tahfidz_new_note', 'tahfidz_murojaah', 'tahfidz_murojaah_note')
            ->get();


        if ($laporan->isEmpty()) { // Cek apakah laporan kosong
            return response()->json([
                'is_correct' => false,
                'message' => 'data not found'
            ], 400);
        }

        //dd($laporan);
        $laporanData = [];

        foreach ($laporan as $item) {
            $laporanData[] = [
                'id' => $item->tahfidz_id,
                'tanggal' => $item->tahfidz_date,
                'jumlah ayat baru' => $item->tahfidz_new,
                'ket.hafalan baru' => $item->tahfidz_new_note,
                'murojaah' => $item->tahfidz_murojaah,
                'murojaah hafalan baru' => $item->tahfidz_murojaah_note
            ];
        }

        $response = [
            'is_correct' => true,
            'message' => 'success',
            'data' => $laporanData
        ];

        return response()->json($response);
    }

    /**
     * @OA\Delete(
     *     path="/api/laporan-tahfidz/{tahfidz_id}",
     *     summary="Delete laporan tahfidz",
     *     description="Menghapus data laporan tahfidz berdasarkan ID",
     *     tags={"Tahfidz"},
     *     security={{"BearerAuth": {}}},
     *     @OA\Parameter(
     *         name="tahfidz_id",
     *         in="path",
     *         required=true,
     *         description="ID tahfidz untuk menghapus data laporan",
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Data berhasil dihapus",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="data berhasil dihapus")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Data tidak ditemukan",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="data tidak ditemukan")
     *         )
     *     )
     * )
     */


    public function delete($tahfidz_id)
    {
        //  $Model = $this->databaseSwitcher->switchDatabaseFromToken(new Tahfidz());
        $data = DB::table('tahfidz')->where('tahfidz_id', $tahfidz_id)->first();
        //$data = Tahfidz::where('tahfidz_id', $tahfidz_id)->first();

        if (!$data) {
            return response()->json([
                'is_correct' => false,
                'message' => 'data tidak ditemukan'
            ], 400);
        }

        // $data->delete('tahfidz_id');
        DB::table('tahfidz')->where('tahfidz_id', $tahfidz_id)->delete();
        return response()->json([
            'is_correct' => true,
            'message' => 'data berhasil dihapus'
        ], 200);
    }

    /**
     * @OA\Patch(
     *     path="/api/laporan-tahfidz/{tahfidz_id}",
     *     summary="Update sebagian laporan tahfidz",
     *     description="Memperbarui sebagian data laporan tahfidz berdasarkan ID",
     *     tags={"Tahfidz"},
     *     security={{"BearerAuth": {}}},
     *     @OA\Parameter(
     *         name="tahfidz_id",
     *         in="path",
     *         required=true,
     *         description="ID tahfidz untuk memperbarui data laporan",
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Data yang ingin diperbarui pada laporan tahfidz",
     *         @OA\JsonContent(
     *             @OA\Property(property="tahfidz_date", type="string", format="date", example="2024-10-14", description="Tanggal laporan tahfidz"),
     *             @OA\Property(property="tahfidz_new", type="integer", example=2, description="Jumlah ayat baru yang dihafal"),
     *             @OA\Property(property="tahfidz_new_note", type="string", example="Keterangan hafalan baru", description="Catatan untuk hafalan baru"),
     *             @OA\Property(property="tahfidz_murojaah", type="string", example="Surat Al-Fatihah", description="Surat yang di-murojaah"),
     *             @OA\Property(property="tahfidz_murojaah_note", type="string", example="Murojaah mingguan", description="Catatan untuk murojaah")
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
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(property="tahfidz_date", type="array", @OA\Items(type="string", example="The tahfidz_date field is required.")),
     *                 @OA\Property(property="tahfidz_new", type="array", @OA\Items(type="string", example="The tahfidz_new field is required."))
     *             )
     *         )
     *     )
     * )
     */

    public function update(Request $request, $tahfidz_id)
    {
        // $this->databaseSwitcher->switchDatabaseFromToken(new major());
        //$data = Tahfidz::where('tahfidz_id', $tahfidz_id)->first();
        $data = DB::table('tahfidz')->where('tahfidz_id', $tahfidz_id)->first();

        if (is_null($data)) {
            return response()->json([
                'is_correct' => true,
                'message' => 'data tidak ditemukan'
            ], 200);
        };

        $validator = Validator::make($request->all(), [
            'tahfidz_date' => 'required|date',
            'tahfidz_new' => 'required',
            'tahfidz_new_note' => 'required',
            'tahfidz_murojaah' => 'required',
            'tahfidz_murojaah_note' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'is_correct' => false,
                'message' => $validator->error()
            ]);
        }
        $data->tahfidz_date = $request->input('tahfidz_date');
        $data->tahfidz_new = $request->input('tahfidz_new');
        $data->tahfidz_new_note = $request->input('tahfidz_new_note');
        $data->tahfidz_murojaah = $request->input('tahfidz_murojaah');
        $data->tahfidz_murojaah_note = $request->input('tahfidz_murojaah_note');

        // $data->save();
        $data = DB::table('tahfidz')->where('tahfidz_id', $tahfidz_id)->update([
            'tahfidz_date' => $request->input('tahfidz_date'),
            'tahfidz_new' => $request->input('tahfidz_new'),
            'tahfidz_new_note' => $request->input('tahfidz_new_note'),
            'tahfidz_murojaah' => $request->input('tahfidz_murojaah'),
            'tahfidz_murojaah_note' => $request->input('tahfidz_murojaah_note')
        ]);

        return response()->json([
            'is_correct' => true,
            'message' => 'Data berhasil diupdate'
        ], 200);
    }
}
