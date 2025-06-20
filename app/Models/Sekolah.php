<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Config;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Sekolah extends Model
{

    use HasFactory;

    // Default connection for the model
    protected $connection = 'mysql';
    protected $table = 'sekolahs';
    protected $guarded = ['id'];
    protected $database_name;
    // Method untuk set nama database
    public function setDatabaseName($nama_sekolah)
    {
        $this->database_name = $nama_sekolah; // Set nama database
        //dd($this->database_name);
    }

    // Method untuk switch database
    public function switchDatabase()
    {
        // Set database name secara dinamis
        if ($this->database_name) {
            $this->customDatabase($this->database_name);
            Config::set('database.connections.sekolah_dynamic.database', $this->database_name);
            DB::purge('mysql'); // Bersihkan koneksi lama
            DB::reconnect('mysql'); // Reconnect ke database baru
            DB::setDefaultConnection('sekolah_dynamic'); // Ganti koneksi default

        }
    }

    public function customDatabase($databaseName)
    {


        if ($databaseName == 'epesantr_demo') {
            Config::set('database.connections.sekolah_dynamic.host', '103.93.130.71');
            Config::set('database.connections.sekolah_dynamic.username', 'epesantr_demonstran');
            Config::set('database.connections.sekolah_dynamic.password', '5[RB*SpX,X0)p#mXn2');
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
        Config::set('database.connections.sekolah_dynamic.host', '103.93.130.71');
        Config::set('database.connections.sekolah_dynamic.username', 'epesantr_semua');
        Config::set('database.connections.sekolah_dynamic.password', '5[RB*SpX,X0)p#mXn2');
        // dd('coba');
    }



    public function getDatabaseName()
    {
        return $this->database_name;
    }
    public function getSchoolName()
    {
        return $this->nama_sekolah;
    }
}
