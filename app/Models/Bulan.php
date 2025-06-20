<?php

namespace App\Models;

use App\Models\Pos;
use App\Models\Month;
use App\Models\Account;
use App\Models\Payment;
use App\Models\Student;
use App\Models\BatchItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Bulan extends Model
{
    use HasFactory;
    protected $table = 'bulan';
    protected $guarded = ['bulan_id'];
    protected $primaryKey = 'bulan_id';
    public $timestamps = false;

    public function batchItem()
    {
        return $this->belongsTo(BatchItem::class, 'payment_payment_id', 'batch_item_payment_id');
    }


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

    public function period()
    {
        return $this->belongsTo(Period::class, 'period_period_id', 'period_id');
    }

    public function account()
    {
        return $this->belongsTo(Account::class, 'bulan_account_id', 'account_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_user_id');
    }
}
