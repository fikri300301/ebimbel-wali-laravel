<?php

namespace App\Models;

use App\Models\Pos;
use App\Models\Bulan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Account extends Model
{
    use HasFactory;
    protected $table = 'account';
    protected $guarded = ['account_id'];
    protected $primaryKey = 'account_id';

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

    public function bulans()
    {
        return $this->hasMany(Bulan::class, 'bulan_account_id', 'account_id');
    }

    public function pos()
    {
        return $this->hasMany(Pos::class, 'account_account_id', 'account_id');
    }
}
