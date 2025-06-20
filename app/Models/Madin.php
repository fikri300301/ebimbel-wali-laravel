<?php

namespace App\Models;

use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Madin extends Model
{
    use HasFactory;
    protected $guarded = ['madin_id'];
    protected $table = 'madin';
    protected $primaryKey = 'madin_id';

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

    public function student()
    {
        return $this->hasMany(Student::class, 'class_class_id', 'class_id');
    }
}
