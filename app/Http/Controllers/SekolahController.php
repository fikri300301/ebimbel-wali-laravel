<?php

namespace App\Http\Controllers;

use App\Models\Sekolah;
use Illuminate\Http\Request;
use App\Services\DatabaseSwitcher;
use Illuminate\Support\Facades\DB;

class SekolahController extends Controller
{
    protected $databaseSwitcher;

    // Inject DatabaseSwitcher di constructor
    public function __construct(DatabaseSwitcher $databaseSwitcher)
    {
        $this->databaseSwitcher = $databaseSwitcher;
    }

    public function index(Request $request)
    {
        try {
            // Gunakan service untuk switch database
            $sekolahModel = $this->databaseSwitcher->switchForUser(new Sekolah());

            // Ambil data dari tabel 'sekolah' menggunakan koneksi yang telah diubah
            $data = DB::table('sekolah')->get();

            return response()->json([
                'data' => $data,
                'current_database' => DB::connection()->getDatabaseName()
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function index1(Request $request)
    {
        // Mendapatkan nama database dari JWT token
        $currentUser = auth()->user();
        if (!$currentUser) {
            return response()->json(['error' => 'User not authenticated.'], 401);
        }

        // Ambil nama sekolah dari user
        $databaseName = $currentUser->sekolah->nama_sekolah;
        if (empty($databaseName)) {
            return response()->json(['error' => 'Database name is empty.'], 400);
        }

        // Membuat instance model Sekolah dan set database
        $sekolahModel = new Sekolah();
        $sekolahModel->setDatabaseName($databaseName);
        $sekolahModel->switchDatabase();

        // Uji koneksi dengan query sederhana
        try {
            $data = DB::table('sekolah')->get(); // Ganti dengan nama tabel yang sesuai
            //$data = Sekolah::all();
            return response()->json([
                'data' => $data,
                'current_database' => DB::connection()->getDatabaseName()
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Database connection failed: ' . $e->getMessage()], 500);
        }
    }
}
