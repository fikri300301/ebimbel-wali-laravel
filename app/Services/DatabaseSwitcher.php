<?php

namespace App\Services;

use App\Models\Sekolah;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Config;

class DatabaseSwitcher
{
    /**
     * Switch database connection for a given model based on user's school code
     *
     * @param mixed $model
     * @param string $kodeSekolah
     * @return mixed
     * @throws \Exception
     */


    public function switchDatabase($db, $model)
    {
        // Ambil database dari JWT token
        // Set nama database di model dan switch database
        $this->customDatabase($db, false);
        DB::purge('mysql'); // Bersihkan koneksi lama
        DB::reconnect('mysql');
        DB::setDefaultConnection('sekolah_dynamic');
        //dd($model);
        $currentDatabase = DB::connection('sekolah_dynamic')->getDatabaseName();

        if (!$currentDatabase) {
            throw new \Exception("Failed to connect to the specified database.");
        }

        return $model;
    }

    public function customDatabase($databaseName, $isLocal)
    {

        Config::set('database.connections.sekolah_dynamic.database', $databaseName);

        if ($isLocal) {
            Config::set('database.connections.sekolah_dynamic.username', 'root');
            return;
        }
        if ($databaseName == 'ebimbel_demo') {
            Config::set('database.connections.sekolah_dynamic.host', '103.93.130.247');
            Config::set('database.connections.sekolah_dynamic.username', 'ebimbel_semua');
            Config::set('database.connections.sekolah_dynamic.password',  'Qke5z006&3xy*lX388');
            // dd('coba');

            return;
        }

        if ($databaseName == 'alrifaie_database') {
            Config::set('database.connections.sekolah_dynamic.host', '103.41.204.234');
            Config::set('database.connections.sekolah_dynamic.username', 'alrifaie_user');
            Config::set('database.connections.sekolah_dynamic.password', 'acVVeRG))gaH');

            return;
        }
        if ($databaseName == 'andri') {
            Config::set('database.connections.sekolah_dynamic.host', '10.122.25.54');
            Config::set('database.connections.sekolah_dynamic.username', 'andri');
            Config::set('database.connections.sekolah_dynamic.password', 'andri2022');

            return;
        }

        if ($databaseName == 'epesantren_almuhsin') {
            Config::set('database.connections.sekolah_dynamic.host', '10.122.25.54');
            Config::set('database.connections.sekolah_dynamic.username', 'almuhsin');
            Config::set('database.connections.sekolah_dynamic.password', '#almuhsin2024');

            return;
        }


        // Config::set('database.connections.sekolah_dynamic.h', '10.122.25.54');
        Config::set('database.connections.sekolah_dynamic.host', '103.93.130.247');
        Config::set('database.connections.sekolah_dynamic.username', 'ebimbel_semua');
        Config::set('database.connections.sekolah_dynamic.password', 'Qke5z006&3xy*lX388');
        // dd('coba');
    }

    public function setNewEnv($key, $value)
    {
        $envFile = file_get_contents(app()->environmentFilePath());

        // Menentukan pattern yang akan dicari (key dan nilainya)
        $pattern = '/^' . preg_quote($key, '/') . '=.*/m';

        // Jika key ditemukan, ganti nilai yang ada, jika tidak, tambahkan key baru
        if (preg_match($pattern, $envFile)) {
            // Ganti baris yang ada dengan key baru dan nilainya
            $envFile = preg_replace($pattern, $key . '=' . $value, $envFile);
        } else {
            // Tambahkan key baru di akhir file
            $envFile .= PHP_EOL . $key . '=' . $value;
        }

        // Tulis kembali perubahan ke file .env
        file_put_contents(app()->environmentFilePath(), $envFile);
    }
}
