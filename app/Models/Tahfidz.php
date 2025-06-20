<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Tahfidz extends Model
{
    use HasFactory;

    //protected $connection = 'sekolah';
    protected $table = 'tahfidz';
    protected $primaryKey = 'tahfidz_id';
    public $timestamps = false;
    protected $guarded = ['tahfidz_id'];
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

    public function period()
    {
        return $this->belongsTo(Period::class, 'tahfidz_period_id', 'period_id');
    }
}
