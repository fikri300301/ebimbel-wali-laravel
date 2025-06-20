<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PresensiStudent extends Model
{
    use HasFactory;
    public $timestamps = false;
    protected $primaryKey = 'id';
    protected $table = 'presensi_student';
    protected $guarded = ['id'];

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
            Config::set('database.connections.sekolah_dynamic.database', $this->database_name);
            DB::setDefaultConnection('sekolah_dynamic'); // Ganti koneksi default
        }
    }
}
