<?php

namespace App\Models;

use App\Models\Pos;
use App\Models\Bulan;
use App\Models\Period;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Payment extends Model
{
    use HasFactory;
    protected $guarded = ['payment_id'];
    protected $table = 'payment';
    protected $primaryKey = 'payment_id';

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
        return $this->hasMany(Bulan::class, 'payment_payment_id', 'payment_id');
    }

    public function period()
    {
        return $this->belongsTo(Period::class, 'period_period_id', 'period_id');
    }

    public function pos()
    {
        return $this->belongsTo(Pos::class, 'pos_pos_id', 'pos_id');
    }
}
