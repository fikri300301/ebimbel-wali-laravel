<?php

namespace App\Http\Controllers;

use App\Models\AreaAbsensi;
use Illuminate\Http\Request;
use App\Services\DatabaseSwitcher;
use Illuminate\Support\Facades\DB;

class AreaAbsensiController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/area-absensi",
     *     summary="Get data area",
     *     description="Mengambil semua data area absensi",
     *     tags={"Absensi"},
     *     security={{"BearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Data area absensi berhasil diambil",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="nama_area", type="string", example="Kantor Utama"),
     *                     @OA\Property(property="lokasi", type="string", example="Jakarta"),
     *                     @OA\Property(property="deskripsi", type="string", example="Area untuk absensi utama pegawai")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Data area absensi tidak ditemukan",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="error")
     *         )
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
        $this->databaseSwitcher->switchDatabaseFromToken(new AreaAbsensi());
        $data = AreaAbsensi::all();

        if ($data->isEmpty()) {
            return response()->json([
                'is_correct' => false,
                'message' => 'error'
            ], 404);
        }

        return response()->json([
            'is_correct' => true,
            'data' => $data
        ], 200);
    }
}
