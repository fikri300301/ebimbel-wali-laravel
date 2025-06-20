<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use App\Models\PresesnsiPelajaran;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Kelas extends Model
{
    use HasFactory;

    // protected $connection = 'sekolah';

    protected $table = 'class';
    protected $guarded = ['id'];

    public $timestamps = false;
    //protected $primaryKey = 'class_id';
    public function setDatabaseName($nama_sekolah)
    {
        $this->database_name = $nama_sekolah; // Set nama database

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
    public function majors()
    {
        return $this->belongsTo(major::class, 'majors_majors_id', 'majors_id');
    }

    public function student()
    {
        return $this->hasMany(Student::class, 'class_class_id', 'class_id');
    }

    public function presensiPelajaran()
    {
        return $this->hasMany(PresesnsiPelajaran::class, 'class_id');
    }
}
