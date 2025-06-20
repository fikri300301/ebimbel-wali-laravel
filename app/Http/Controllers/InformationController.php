<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Information;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Http\Request;
use App\Services\DatabaseSwitcher;

class InformationController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/information",
     *     summary="Get data information",
     *     description="Mengambil semua data information",
     *     tags={"Information"},
     *     security={{"BearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Data information berhasil diambil",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="information_id", type="integer", example="1"),
     *                     @OA\Property(property="information_title", type="string", example="fitur baru"),
     *                     @OA\Property(property="information_desc", type="integer", example="deskripsi"),
     *                     @OA\Property(property="information_img", type="integer", example="1"),
     *                     @OA\Property(property="information_publish", type="integer", example="1"),
     *                     @OA\Property(property="user_id", type="integer", example="1"),
     *                     @OA\Property(property="sekolah_id", type="integer", example="1"),
     *                     @OA\Property(property="information_input_date", type="date", example="2024-10-12"),
     *                     @OA\Property(property="information_update_date", type="date", example="2024-10-12"),
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

    // // Inject DatabaseSwitcher di constructor
    // public function __construct(DatabaseSwitcher $databaseSwitcher)
    // {
    //     $this->databaseSwitcher = $databaseSwitcher;
    // }
    public function index()
    {
        $token = JWTAuth::parseToken();

        // Get the token payload
        $claims = $token->getPayload();

        $folder = $claims->get('folder');
        //  dd($folder);
        $data = Information::where('information_publish', '1')->get()->map(function ($item) use ($folder) {
            return [
                'id' => $item->information_id,
                'title' => $item->information_title,
                'tanggal' => $item->information_input_date,
                'photo'  => "https://$folder.ebimbel.co.id/uploads/information/" . $item->information_img,
                // 'photo' => $item->
            ];
        });
        return response()->json([
            'is_correct' => true,
            'message' => 'success',
            'information' => $data
        ], 200);
    }

    /**
     * @OA\Get(
     *     path="/api/information-detail/{information_id}",
     *     summary="Get data information",
     *     description="Mengambil semua data information",
     *     tags={"Information"},
     *     security={{"BearerAuth": {}}},
     *  @OA\Parameter(
     *         name="information_id",
     *         in="path",
     *         required=true,
     *         description="ID information untuk mengambil detail data ",
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Data information berhasil diambil",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="information_img", type="integer", example="1"),
     *                     @OA\Property(property="information_input_date", type="date", example="2024-10-12"),
     *                     @OA\Property(property="information_title", type="string", example="fitur baru"),
     *                     @OA\Property(property="information_desc", type="integer", example="deskripsi"),
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

    public function show($id)
    {
        $token = JWTAuth::parseToken();

        // Get the token payload
        $claims = $token->getPayload();

        $folder = $claims->get('folder');

        //  $this->databaseSwitcher->switchDatabaseFromToken(new Information());
        $data = Information::where('information_id', $id)->first();

        if (is_null($data)) {
            return response()->json([
                'is_correct' => false,
                'message' => 'informasi tidak ditemukan'
            ]);
        }

        $formatedDate = Carbon::parse($data->information_input_date)
            ->locale('id')
            ->isoFormat('dddd, D MMMM YYYY');

        return response()->json([
            'is_correct' => true,
            'message' => 'success',
            'information_detail' => [
                'foto' => "https://$folder.ebimbel.co.id/uploads/information/" . $data->information_img,

                'date' => $data->information_input_date,
                'title' => $data->information_title,
                'information_desc' => $data->information_desc
            ]

        ]);
    }
}
