<?php

namespace App\Http\Controllers;

use App\Models\day;
use Illuminate\Http\Request;
use App\Services\DatabaseSwitcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DayController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/day",
     *     summary="Get data hari",
     *     tags={"Day"},
     *     security={{"BearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Berhasil mendapatkan data",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean"),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(
     *                 property="unit",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="day_id", type="integer"),
     *                     @OA\Property(property="day_name", type="string")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Token tidak valid."
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Data tidak ditemukan."
     *     )
     * )
     */

    protected $databaseSwitcher;

    // Inject DatabaseSwitcher di constructor
    public function __construct(DatabaseSwitcher $databaseSwitcher)
    {
        $this->databaseSwitcher = $databaseSwitcher;
    }
    public function index()
    {
        $Model = $this->databaseSwitcher->switchDatabaseFromToken(new day());
        $day = day::all();
        // $day = DB::table('day')->get();
        if (is_null($day)) {
            return response()->json([
                'success' => false,
                'message' => 'data belum tersedia'
            ], 404);
        }

        return response()->json([
            'is_corect' => true,
            'message' => 'success',
            'unit' => $day
        ], 200);
    }

    public function show($day_id)
    {
        $day = DB::connection('sekolah')->table('day')->where('day_id', $day_id)->first();

        if (is_null($day)) {
            return response()->json([
                'success' => false,
                'message' => 'data belum ditemukan'
            ], 404);
        }

        return response()->json([
            'data' => $day
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'day_name' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'error',
                'error' => $validator->errors()
            ], 404);
        }

        $day = DB::connection('sekolah')->table('day')->insertGetId([
            'day_name' => $request->day_name
        ]);

        $day = DB::connection('sekolah')->table('day')->where('day_id', $day)->first();
        return response()->json([
            'message' => 'data berhasil dibuat',
            'data' => $day
        ], 200);
    }

    public function destroy($day_id)
    {
        $day = DB::connection('sekolah')->table('day')->where('day_id', $day_id)->first();

        if ($day) {
            DB::connection('sekolah')->table('day')->where('day_id', $day_id)->delete();
            return response()->json([
                'message' => 'data berhasil dihapus',
                'data' => $day
            ], 200);
        } else {
            return response()->json([
                'message' => 'data gagal dihapus'
            ], 404);
        }
    }

    public function update(Request $request, $day_id)
    {
        $validator = Validator::make($request->all(), [
            'day_name' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'error',
                'error' => $validator->errors()
            ], 404);
        }

        $day = DB::connection('sekolah')->table('day')->where('day_id', $day_id)->first();
        if (!$day) {
            return response()->json([
                'message' => 'data tidak ditemukan'
            ], 404);
        }

        $data_update = [];
        if ($request->filled('day_name')) {
            $data_update['day_name'] = $request->day_name;
        }

        DB::connection('sekolah')->table('day')->where('day_id', $day_id)->update($data_update);

        return response()->json([
            'message' => 'Data berhasil diperbarui',
            'data' => DB::connection('sekolah')->table('day')->where('day_id', $day_id)->first()
        ], 200);
    }
}
