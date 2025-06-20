<?php

namespace App\Models;

use App\Models\AreaAbsensi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Employee extends Authenticatable implements JWTSubject
{
    use HasFactory;
    public $timestamps = false;
    protected $primaryKey = 'employee_id';

    protected $guarded = ['employee_id'];

    protected $connection;

    protected $table = 'employee';

    protected $hidden = ['employee_password'];
    public function setDatabaseName($nama_sekolah)
    {
        $this->database_name = $nama_sekolah;
        //  dd($this->database_name = $nama_sekolah);
    }

    // Method untuk switch database
    public function switchDatabase()
    {
        //dd($this->database_name);
        // Set database name secara dinamis
        if ($this->database_name) {
            Config::set('database.connections.sekolah_dynamic.database', $this->database_name);
            DB::setDefaultConnection('sekolah_dynamic'); // Ganti koneksi default
        }
    }

    /**
     * Get the identifier that will be stored in the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        //return $this->getKey(); // Mengembalikan ID kunci utama (biasanya 'employee_id')
        return $this->employee_id;
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return []; // Jika Anda ingin menambahkan klaim khusus, tambahkan di sini
    }

    public function getAuthPassword()
    {
        return $this->employee_password;
    }

    public function position()
    {
        // Gunakan 'employee_position_id' sebagai foreign key dan 'position_id' sebagai primary key
        return $this->belongsTo(Position::class, 'employee_position_id', 'position_id');
    }

    public function sekolah()
    {
        return $this->belongsTo(Sekolah::class, 'sekolah_id', 'id');
    }

    public function areaAbsensi()
    {
        return $this->belongsTo(AreaAbsensi::class, 'area_absen', 'id_area');
    }
}
