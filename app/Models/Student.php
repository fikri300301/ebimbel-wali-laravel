<?php

namespace App\Models;

use App\Models\Bulan;
use App\Models\Madin;
use App\Models\Tahfidz;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Database\Eloquent\Model;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Student extends Authenticatable implements JWTSubject
{
    use HasFactory;

    // protected $connection = 'sekolah';

    protected $table = 'student';
    protected $primaryKey = 'student_id';
    protected $guarded = ['student_id'];
    public $timestamps = false;
    public function setDatabaseName($nama_sekolah)
    {
        $this->database_name = $nama_sekolah; // Set nama database

    }

    // Method untuk switch database
    public function switchDatabase()
    {
        // Set database name secara dinamis
        if ($this->database_name) {
            $this->customDatabase($this->database_name);
            Config::set('database.connections.sekolah_dynamic.database', $this->database_name);
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

    public function kelas()
    {
        return $this->belongsTo(Kelas::class, 'class_class_id', 'class_id');
    }

    public function madin()
    {
        return $this->belongsTo(Madin::class, 'student_madin', 'madin_id');
    }

    public function major()
    {
        return $this->belongsTo(major::class, 'majors_majors_id', 'majors_id');
    }

    public function tahfidz()
    {
        return $this->hasMany(Tahfidz::class, 'tahfidz_student_id', 'student_id');
    }

    public function bulans()
    {
        return $this->hasMany(Bulan::class, 'student_student_id', 'student_id');
    }

    public function getJWTIdentifier()
    {
        //return $this->getKey(); // Mengembalikan ID kunci utama (biasanya 'employee_id')
        return $this->student_id;
    }

    public function getJWTCustomClaims()
    {
        return []; // Jika Anda ingin menambahkan klaim khusus, tambahkan di sini
    }

    public function getAuthPassword()
    {
        return $this->student_password;
    }
}
