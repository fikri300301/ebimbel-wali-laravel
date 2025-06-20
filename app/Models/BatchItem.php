<?php

namespace App\Models;

use App\Models\BatchPayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BatchItem extends Model
{
    use HasFactory;

    protected $guarded = ['batch_item_id'];
    protected $table = 'batch_item';
    protected $primaryKey = 'batch_item_id';
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
    public function batchPayment()
    {
        return $this->belongsTo(BatchPayment::class, 'batch_item_batchpayment_id', 'id');
    }
}
