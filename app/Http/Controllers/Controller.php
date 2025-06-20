<?php

namespace App\Http\Controllers;

use App\Models\major;
use App\Services\DatabaseSwitcher;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * @OA\Info(
 *      version="1.0.0",
 *      title="Dokumentasi API epesantren pegawai",
 *      description="Dokumentasi API epesantren pegawai",
 *      @OA\Contact(
 *          email="fp3175723@gmail.com"
 *      ),
 *      @OA\License(
 *          name="Apache 2.0",
 *          url="http://www.apache.org/licenses/LICENSE-2.0.html"
 *      )
 * )
 *
 * @OA\Server(
 *      url=L5_SWAGGER_CONST_HOST,
 *      description="Demo API Server"
 * )
 */

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;
    protected $databaseSwitcher;

    public function __construct(DatabaseSwitcher $databaseSwitcher)
    {
        $this->databaseSwitcher = $databaseSwitcher;

        // Hanya parsing JWT jika permintaan datang dari API
        if (request()->is('api/*')) {
            try {
                $token = JWTAuth::parseToken();
                // Parse token dari header Authorization
                $claims = $token->getPayload(); // Ambil payload token

                $this->databaseSwitcher->switchDatabase($claims['db'], new major());
                // dd($this->databaseSwitcher->switchDatabaseFromToken(new major()));
            } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
                return $e;
            }
        }
    }
}
