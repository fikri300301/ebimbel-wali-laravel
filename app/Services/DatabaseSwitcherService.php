<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

class DatabaseSwitcherService
{
    protected $databaseName;

    public function __construct()
    {
        $this->databaseName = null;
    }

    public function setDatabaseName($databaseName)
    {
        if (!$databaseName) {
            throw new \Exception("Database name cannot be empty.");
        }

        $this->databaseName = $databaseName;

        // Update konfigurasi koneksi dinamis
        $this->customDatabase($this->databaseName);
        Config::set('database.connections.sekolah_dynamic.database', $this->databaseName);


        // Membersihkan dan reconnect koneksi
        DB::purge('mysql'); // Bersihkan koneksi lama
        DB::reconnect('mysql');
        DB::setDefaultConnection('sekolah_dynamic');

        // Pastikan koneksi berhasil dengan mengetes nama database
        $currentDatabase = DB::connection('sekolah_dynamic')->getDatabaseName();

        if (!$currentDatabase) {
            throw new \Exception("Failed to connect to the specified database.");
        }
    }

    public function customDatabase($databaseName)
    {
        if ($databaseName == 'epesantr_demo') {
            Config::set('database.connections.sekolah_dynamic.host', '103.93.130.71');
            Config::set('database.connections.sekolah_dynamic.username', 'epesantr_demonstran');
            Config::set('database.connections.sekolah_dynamic.password', '5[RB*SpX,X0)p#mXn2');

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
        Config::set('database.connections.sekolah_dynamic.host', '103.93.130.71');
        Config::set('database.connections.sekolah_dynamic.username', 'epesantr_semua');
        Config::set('database.connections.sekolah_dynamic.password', '5[RB*SpX,X0)p#mXn2');
        //dd('coba');
    }

    public function getDatabaseName()
    {
        return $this->databaseName;
    }
}
