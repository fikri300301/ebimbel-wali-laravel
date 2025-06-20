<?php

namespace App\Models;

use App\Models\Bebas;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BebasPayMobile extends Model
{
    use HasFactory;

    protected $guarded = ['bebas_pay_id'];
    protected $table = 'bebas_pay_mobile';
    protected $primaryKey = 'bebas_pay_id';
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
    public function month()
    {
        return $this->belongsTo(Month::class, 'month_month_id', 'month_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_student_id', 'student_id');
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_payment_id', 'payment_id');
    }

    public function pos()
    {
        return $this->belongsTo(Pos::class, 'pos_pos_id', 'pos_id');
    }

    public function bebas()
    {
        return $this->belongsTo(Bebas::class, 'bebas_bebas_id', 'bebas_id');
    }
}
