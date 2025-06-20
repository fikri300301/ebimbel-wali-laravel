<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use Illuminate\Http\Request;
use App\Services\DatabaseSwitcher;
use Illuminate\Support\Facades\DB;

class KelasController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/data-kelas",
     *     summary="Get data kelas",
     *     tags={"List Unit class and student"},
     *     security={{"BearerAuth":{}}},
     *     @OA\Parameter(
     *         name="class_id",
     *         in="query",
     *         required=true,
     *         description="ID sekolah",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="class_name",
     *         in="query",
     *         required=true,
     *         description="Nama kelas",
     *         @OA\Schema(type="string")
     *     ),
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
     *                     @OA\Property(property="id_kelas", type="integer"),
     *                     @OA\Property(property="nama_kelas", type="string")
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
    // protected $databaseSwitcher;
    // public function __construct(DatabaseSwitcher $databaseSwitcher)
    // {
    //     $this->databaseSwitcher = $databaseSwitcher;
    // }

    public function index(Request $request)
    {
        //$this->databaseSwitcher->switchDatabaseFromToken(new Kelas());
        $class_id = $request->input('class_id');
        $class_name = $request->input('class_name');

        $data = DB::table('class')->where('class_id', $class_id)->where('class_name', $class_name)->first();
        if (is_null($data)) {
            return response()->json([
                'is_correct' => false,
                'message' => 'Anda tidak terdaftar.',
            ], 404);
        }

        return response()->json([
            'is_correct' => true,
            'message' => 'success',
            'data' => [
                'id_kelas' => $data->class_id,
                'nama_kelas' => $data->class_name,
            ]
        ], 200);
    }
}
