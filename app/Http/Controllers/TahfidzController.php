<?php

namespace App\Http\Controllers;

use App\Models\Tahfidz;
use Illuminate\Http\Request;
use App\Services\DatabaseSwitcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;


class TahfidzController extends Controller
{
    // protected $databaseSwitcher;

    // // Inject DatabaseSwitcher di constructor
    // public function __construct(DatabaseSwitcher $databaseSwitcher)
    // {
    //     $this->databaseSwitcher = $databaseSwitcher;
    // }
    /**
     * @OA\Post(
     *     path="/api/tahfidz",
     *     summary="Membuat data tahfidz",
     *     description="Mengirim data Tahfidz",
     *     tags={"Tahfidz"},
     *     security={{"BearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         content={
     *             @OA\MediaType(
     *                 mediaType="multipart/form-data",
     *                 @OA\Schema(
     *                     required={"tahfidz_period_id", "tahfidz_student_id", "tahfidz_date", "tahfidz_new", "tahfidz_new_note", "tahfidz_murojaah", "tahfidz_murojaah_note"},
     *                     @OA\Property(property="tahfidz_period_id", type="string", example="1", description="ID Periode Tahfidz"),
     *                     @OA\Property(property="tahfidz_student_id", type="string", example="2", description="ID Siswa"),
     *                     @OA\Property(property="tahfidz_date", type="string", format="date", example="2024-10-28", description="Tanggal tahfidz (YYYY-MM-DD)"),
     *                     @OA\Property(property="tahfidz_new", type="string", example="10", description="Tahfidz New"),
     *                     @OA\Property(property="tahfidz_new_note", type="string", example="Menambahkan 10 ayat", description="Tahfidz New Note"),
     *                     @OA\Property(property="tahfidz_murojaah", type="string", example="5", description="Tahfidz Murojaah"),
     *                     @OA\Property(property="tahfidz_murojaah_note", type="string", example="Murojaah untuk persiapan ujian", description="Tahfidz Murojaah Note")
     *                 )
     *             )
     *         }
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Data absensi berhasil disimpan",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="success")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validasi data absensi gagal",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=false),
     *             @OA\Property(property="error", type="object", additionalProperties={"type": "string"})
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $period = $request->query('period');

        $query = Tahfidz::where('tahfidz_student_id', $user->student_id);

        if ($period) {
            $query->where('tahfidz_period_id', $period);
        }

        $query->orderBy('tahfidz_date', 'desc');
        $data = $query->get()->map(function ($item) {
            return  [
                'date' => $item->tahfidz_date,
                'jumlah_hafalan' => (int)$item->tahfidz_new,
                'tahfidz_new_note' => $item->tahfidz_new_note,
                'murojaah' => $item->tahfidz_murojaah,
                'murojaah_note' => $item->tahfidz_murojaah_note,
                'period_id' => $item->tahfidz_period_id,
                'tahun_ajaran' => $item->period ? $item->period->period_start . '/' . $item->period->period_end : null
            ];
        });

        $jumlahHafalan = Tahfidz::where('tahfidz_student_id', $user->student_id)->sum('tahfidz_new');
        //   dd($data);
        if ($data) {
            return response()->json([
                'is_correct' => true,
                'message' => 'success',
                'nama' => $user->student_full_name,
                'kelas' => $user->kelas->class_name,
                'jumlah_hafalan' => $jumlahHafalan,
                'tahfidz' => $data
            ]);
        }
    }

    public function store(Request $request)
    {
        //$Model = $this->databaseSwitcher->switchDatabaseFromToken(new Tahfidz());

        $user = auth()->user();

        $validator = Validator::make($request->all(), [
            'tahfidz_period_id' => 'required',
            'tahfidz_student_id' => 'required',
            'tahfidz_date' => 'required|date',
            'tahfidz_new' => 'required',
            'tahfidz_new_note' => 'required',
            'tahfidz_murojaah' => 'required',
            'tahfidz_murojaah_note' => 'required',
            'tahfidz_user_id' => 'nullable',
            'tahfidz_input_date' => 'nullable',
            'tahfidz_last_update' => 'nullabe',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'is_correct' => false,
                'error' => $validator->errors()
            ], 400);
        }

        $dataUntukDikirim = [
            'tahfidz_period_id' => $request->tahfidz_period_id,
            'tahfidz_student_id' => $request->tahfidz_student_id,
            'tahfidz_date' => $request->tahfidz_date,
            'tahfidz_new' => $request->tahfidz_new,
            'tahfidz_new_note' => $request->tahfidz_new_note,
            'tahfidz_murojaah' => $request->tahfidz_murojaah,
            'tahfidz_murojaah_note' => $request->tahfidz_murojaah_note,
            'tahfidz_user_id' => $user->employee_id,
            'tahfidz_input_date' => now(),
            'tahfidz_last_update' => now()
        ];

        $data = DB::table('tahfidz')->insertGetId($dataUntukDikirim);
        return response()->json([
            'is_correct' => true,
            'message' => 'success'
        ], 200);
    }
}
