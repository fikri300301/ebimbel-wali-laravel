<?php

namespace App\Models;

use App\Models\Kelas;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PresesnsiPelajaran extends Model
{
    use HasFactory;

    protected $guarded = ['presensi_pelajaran_id'];

    //protected $connection = 'sekolah';
    protected $table = 'presensi_pelajaran';

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
    public function kelas()
    {
        return $this->belongsTo(Kelas::class, 'presensi_pelajaran_class_id', 'class_id');
    }

    public function lesson()
    {
        return $this->belongsTo(Lesson::class,  'presensi_pelajaran_lesson_id', 'lesson_id');
    }
}
