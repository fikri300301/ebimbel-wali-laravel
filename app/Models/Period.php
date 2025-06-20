<?php

namespace App\Models;

use App\Models\Bulan;
use App\Models\Semester;
use App\Models\PengajuanIzin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Period extends Model
{
    use HasFactory;

    //protected $connection = 'sekolah';

    protected $table = 'period';

    protected $guarded = ['period_id'];

    protected $primaryKey = 'period_id';

    public $timestamps = false;

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

    public function pengajuanIzins()
    {
        return $this->hasMany(PengajuanIzin::class, 'pengajuan_izin_period_id', 'period_id');
    }

    public function semesters()
    {
        return $this->hasMany(Semester::class, 'semester_period_id', 'period_id');
    }
    public function bulans()
    {
        return $this->hasMany(Bulan::class, 'period_period_id', 'period_id');
    }
}
