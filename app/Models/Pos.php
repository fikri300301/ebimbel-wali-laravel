<?php

namespace App\Models;

use App\Models\Bulan;
use App\Models\Account;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Pos extends Model
{
    use HasFactory;

    protected $guarded = ['pos_id'];
    protected $table = 'pos';
    protected $primaryKey = 'pos_id';
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

    public function bulans()
    {
        return $this->hasMany(Bulan::class, 'pos_pos_id', 'pos_id');
    }

    //  public function bebas()

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_account_id', 'account_id');
    }
}
