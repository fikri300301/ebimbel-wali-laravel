<?php

namespace App\Http\Controllers;

use App\Models\Month;
use Illuminate\Http\Request;
use App\Services\DatabaseSwitcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MonthController extends Controller
{
    //protected $databaseSwitcher;

    // Inject DatabaseSwitcher di constructor
    // public function __construct(DatabaseSwitcher $databaseSwitcher)
    // {
    //     $this->databaseSwitcher = $databaseSwitcher;
    // }
    /**
     * @OA\Get(
     *     path="/api/month",
     *     summary="Get data month",
     *     tags={"Month"},
     *     security={{"BearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Berhasil mendapatkan data",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean"),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="month_id", type="integer"),
     *                     @OA\Property(property="month_name", type="string")
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

    // public function __construct()
    // {
    //     $this->middleware('auth');
    // }
    public function index()
    {
        $user = auth()->user();
        // $user;
        $months = Month::all();
        if (is_null($months)) {
            return response()->json([
                'is_correct' => false,
                'message' => 'kode sekolah anda salah'
            ], 404);
        }
        return response()->json([
            'is_correct' => true,
            'message' => 'success',
            // 'coba' => 'coba',
            'month' => $months->map(function ($month) {
                return [
                    'month_id' => $month->month_id,
                    'month_name' => $month->month_name,
                ];
            })
        ]);
    }

    // public function show($monthId)
    // {
    //     $month = DB::connection('sekolah')->table('month')->where('month_id', $monthId)->first();

    //     if (is_null($month)) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'data tida ditemukan'
    //         ], 404);
    //     }

    //     return response()->json([
    //         'data' => $month
    //     ], 200);
    // }

    // public function store(Request $request)
    // {
    //     $validator = Validator::make($request->all(), [
    //         'month_name' => 'required',
    //         'sekolah_id' => 'required'
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'message' => 'error',
    //             'error' => $validator->errors()
    //         ], 404);
    //     }

    //     $month = DB::connection('sekolah')->table('month')->insertGetId([
    //         'month_name' => $request->month_name,
    //         'sekolah_id' => $request->sekolah_id

    //     ]);

    //     $month = DB::connection('sekolah')->table('month')->where('month_id', $month)->first();

    //     return response()->json([
    //         'message' => 'data berhasil ditambahkan',
    //         'data' => $month
    //     ], 200);
    // }

    // public function destroy($monthId)
    // {
    //     $month = DB::connection('sekolah')->table('month')->where('month_id', $monthId)->first();

    //     if ($month) {
    //         DB::connection('sekolah')->table('month')->where('month_id', $monthId)->delete();
    //         return response()->json([
    //             'message' => 'data berhasil dihapus',
    //             'data' => $month
    //         ], 200);
    //     } else {
    //         return response()->json([
    //             'message' => 'data gagal dihapus'
    //         ], 404);
    //     }
    // }

    // public function update(Request $request, $monthId)
    // {

    //     $month = DB::connection('sekolah')->table('month')->where('month_id', $monthId)->first();

    //     if (!$month) {
    //         return response()->json([
    //             'message' => 'data tidak ditemukan'
    //         ], 404);
    //     }

    //     $validator = Validator::make($request->all(), [
    //         'month_name' => 'required',
    //         'sekolah_id' => 'required'
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'message' => 'error',
    //             'error' => $validator->errors()
    //         ], 404);
    //     }



    //     $data_update = [];
    //     if ($request->filled('month_name')) {
    //         $data_update['month_name'] = $request->month_name;
    //     }

    //     if ($request->filled('sekolah_id')) {
    //         $data_update['sekolah_id'] = $request->sekolah_id;
    //     }

    //     DB::connection('sekolah')->table('month')->where('month_id', $monthId)->update($data_update);

    //     return response()->json([
    //         'message' => 'success',
    //         'data' => DB::connection('sekolah')->table('month')->where('month_id', $monthId)->first()
    //     ], 200);
    // }
}
