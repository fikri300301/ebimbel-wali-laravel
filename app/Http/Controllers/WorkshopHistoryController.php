<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\workshopHistory;
use App\Services\DatabaseSwitcher;
use Illuminate\Support\Facades\Validator;

class WorkshopHistoryController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/auth/workshop-history",
     *     summary="Get data workshop history",
     *     description="Mengambil semua data workshop history berdasarkan user login",
     *     tags={"Profil_Riwayat_hidup-Workshop"},
     *     security={{"BearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Data workshop history berhasil diambil",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="workshop_id", type="string", example="1"),
     *                     @OA\Property(property="workshop_start", type="string", example="2000"),
     *                     @OA\Property(property="workshop_end", type="string", example="2024"),
     *                     @OA\Property(property="workshop_organizer", type="string", example="ilmu pengetahuan alam"),
     *                     @OA\Property(property="workshop_location", type="integer", example="118"),
     *                     @OA\Property(property="workshop_employee_id", type="integer", example="118"),
     *                     @OA\Property(property="sekolah_id", type="integer", example="1"),
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="null",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="error")
     *         )
     *     )
     * )
     */
    // protected $databaseSwitcher;
    // public function __construct(DatabaseSwitcher $databaseSwitcher)
    // {
    //     $this->databaseSwitcher = $databaseSwitcher;
    // }
    public function index()
    {
        // $this->databaseSwitcher->switchDatabaseFromToken(new workshopHistory());
        $user = auth()->user();
        $data = workshopHistory::where('workshop_employee_id', $user->employee_id)->get();
        if ($data->isEmpty()) {
            return response()->json([
                'is_correct' => false,
                'message' => 'data belum tersedia'
            ], 200);
        }

        return response()->json([
            'is_correct' => true,
            'message' => 'success',
            'data' => $data
        ], 200);
    }
    /**
     *  @OA\Get(
     * path="/api/auth/workshop-history/{id}",
     * summary="Get data workshop history",
     * description="Mengambil detail data workshop history berdasarkan user login",
     * tags={"Profil_Riwayat_hidup-Workshop"},
     * security={{"BearerAuth": {}}},
     * @OA\Parameter(
     *     name="id",
     *     in="path",
     *    description="ID dari workshop yang ingin diambil",
     *    required=true,
     *    @OA\Schema(
     *           type="integer",
     *          example=5
     *       )
     *  ),
     * @OA\Response(
     *     response=200,
     *     description="Data workshop history berhasil diambil",
     *     @OA\JsonContent(
     *         @OA\Property(property="is_correct", type="boolean", example=true),
     *          @OA\Property(
     *             property="data",
     *              type="array",
     *              @OA\Items(
     *                  @OA\Property(property="workshop_id", type="integer", example=1),
     *                   @OA\Property(property="workshop_start", type="string", format="date", example="2024-06-12"),
     *                 @OA\Property(property="workshop_end", type="string", format="date", example="2024-06-24"),
     *                   @OA\Property(property="workshop_organizer", type="string", example="boxing"),
     *                   @OA\Property(property="workshop_location", type="string", example="kediri"),
     *                    @OA\Property(property="workshop_employee_id", type="integer", example=118),
     *                   @OA\Property(property="sekolah_id", type="integer", example=0)
     *               )
     *      )
     *  )
     * ),
     *  @OA\Response(
     *        response=404,
     *       description="Workshop history not found",
     *       @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Workshop history not found")
     *           )
     *        )
     *    )
     */
    public function detail($workshop_id)
    {
        //$this->databaseSwitcher->switchDatabaseFromToken(new workshopHistory());
        $user = auth()->user();
        $data = workshopHistory::where('workshop_id', $workshop_id)->where('workshop_employee_id', $user->employee_id)->first();;

        if ($data) {
            $data = [
                "workshop_id" => $data->workshop_id,
                "workshop_start" => $data->workshop_start,
                "workshop_end" => $data->workshop_end,
                "workshop_organizer" => $data->workshop_organizer,
                "workshop_location" => $data->workshop_location,
            ];
            return response()->json([
                'is_correct' => true,
                'message' => 'success',
                'data' => $data
            ], 200);
        } else {
            return response()->json([
                'is_correct' => false,
                'message' => 'Workshop history not found'
            ], 404);
        }
    }


    /**
     * @OA\Post(
     *     path="/api/auth/workshop-history",
     *     summary="Membuat data workshop history",
     *     description="Mengirim data workshop history",
     *     tags={"Profil_Riwayat_hidup-Workshop"},
     *     security={{"BearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         content={
     *             @OA\MediaType(
     *                 mediaType="multipart/form-data",
     *                 @OA\Schema(
     *                     required={"workshop_start","workshop_end","workshop_organizer","workshop_location"},
     *                     @OA\Property(property="workshop_start", type="string", example="2010-09-30"),
     *                     @OA\Property(property="workshop_end", type="string", example="2010-09-30"),
     *                     @OA\Property(property="workshop_organizer", type="string", example="kemendikbud"),
     *                     @OA\Property(property="workshop_location", type="string", example="Kediri"),
     *                 )
     *             )
     *         }
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Data workshop history berhasil disimpan",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="success")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validasi data  gagal",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=false),
     *             @OA\Property(property="error", type="object", additionalProperties={"type": "string"})
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        // $this->databaseSwitcher->switchDatabaseFromToken(new workshopHistory());
        $validator = Validator::make($request->all(), [
            'workshop_start' => 'required|string',
            'workshop_end' => 'required|string',
            'workshop_organizer' => 'required|string',
            'workshop_location' => 'required|string',
            'workshop_employee_id' => 'nullable',
            'sekolah_id' => 'nullable'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'is_correct' => false,
                'message' => $validator->errors()
            ]);
        }

        $user = auth()->user();
        $employee = $user->employee_id;
        $sekolah = $user->sekolah_id;

        $data = workshopHistory::create([
            'workshop_start' => $request->workshop_start,
            'workshop_end' => $request->workshop_end,
            'workshop_organizer' => $request->workshop_organizer,
            'workshop_location' => $request->workshop_location,
            'workshop_employee_id' => $employee,
            'sekolah_id' => $sekolah
        ]);

        return response()->json([
            'is_correct' => true,
            'message' => 'success'
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/auth/workshop-history/{workshop_id}",
     *     summary="Delete workshop history",
     *     description="Menghapus data workshop history berdasarkan ID",
     *     tags={"Profil_Riwayat_hidup-Workshop"},
     *     security={{"BearerAuth": {}}},
     *     @OA\Parameter(
     *         name="workshop_id",
     *         in="path",
     *         required=true,
     *         description="ID workshop untuk menghapus data ",
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
    public function destroy($workshop_id)
    {
        //$this->databaseSwitcher->switchDatabaseFromToken(new workshopHistory());
        $user = auth()->user();
        $data = workshopHistory::where('workshop_id', $workshop_id)->where('workshop_employee_id', $user->employee_id)->first();
        if ($data) {
            $data->delete();
            return response()->json([
                'is_correct' => true,
                'message' => 'success'
            ], 200);
        } else {
            return response()->json([
                'is_correct' => false,
                'message' => 'data tidak ditemukan'
            ]);
        }
    }

    /**
     * @OA\Patch(
     *     path="/api/auth/workshop-history/{workshop_id}",
     *     summary="update data workshop history",
     *     description="Update data workshop history",
     *     tags={"Profil_Riwayat_hidup-Workshop"},
     *     security={{"BearerAuth": {}}},
     *      *     @OA\Parameter(
     *         name="workshop_id",
     *         in="path",
     *         required=true,
     *         description="ID untuk memperbarui data",
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         content={
     *             @OA\MediaType(
     *                 mediaType="application/json",
     *                 @OA\Schema(
     *                     required={"workshop_start","workshop_end","workshop_organizer","workshop_location"},
     *                     @OA\Property(property="workshop_start", type="string", example="2010-09-30"),
     *                     @OA\Property(property="workshop_end", type="string", example="2010-09-30"),
     *                     @OA\Property(property="workshop_organizer", type="string", example="kemendikbud"),
     *                     @OA\Property(property="workshop_location", type="string", example="Kediri"),
     *                 )
     *             )
     *         }
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Data workshop history berhasil diupate",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="success")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validasi data gagal",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=false),
     *             @OA\Property(property="error", type="object", additionalProperties={"type": "string"})
     *         )
     *     )
     * )
     */
    public function update(Request $request, $workshop_id)
    {
        $this->databaseSwitcher->switchDatabaseFromToken(new workshopHistory());
        $user = auth()->user();
        $data = workshopHistory::where('workshop_id', $workshop_id)->where('workshop_employee_id', $user->employee_id)->first();

        if ($data) {
            $validator = Validator::make($request->all(), [
                'workshop_start' => 'required|string',
                'workshop_end' => 'required|string',
                'workshop_organizer' => 'required|string',
                'workshop_location' => 'required|string',
                'workshop_employee_id' => 'nullable',
                'sekolah_id' => 'nullable'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'is_correct' => false,
                    'message' => $validator->errors()
                ]);
            }

            $user = auth()->user();
            $employee = $user->employee_id;
            $sekolah = $user->sekolah_id;

            $data->workshop_start = $request->input('workshop_start');
            $data->workshop_end = $request->input('workshop_end');
            $data->workshop_organizer = $request->input('workshop_organizer');
            $data->workshop_location = $request->input('workshop_location');
            $data->workshop_employee_id = $employee;
            $data->sekolah_id = $sekolah;

            $data->save();

            return response()->json([
                'is_correct' => true,
                'message' => 'success'
            ]);
        } else {
            return response()->json([
                'is_correct' => false,
                'message' => 'not found '
            ]);
        }
    }
}
