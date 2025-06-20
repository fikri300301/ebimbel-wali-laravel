<?php

namespace App\Models;

use App\Models\AreaAbsensi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class setting_presensi_siswa extends Model
{
    use HasFactory;

    public $timestime = false;
    protected $primaryKey = 'id';
    protected $table = 'setting_presensi_siswa';
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

    public function area()
    {
        return $this->belongsTo(AreaAbsensi::class, 'id_area_absensi', 'id_area');
    }
}
