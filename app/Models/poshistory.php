<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class poshistory extends Model
{
    use HasFactory;
    protected $guarded = ['poshistory_id'];
    // protected $connection = 'sekolah';
    protected $primaryKey = 'poshistory_id';
    protected $table = 'position_history';
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
            // Set database connection secara dinamis
            Config::set('database.connections.sekolah_dynamic.database', $this->database_name);
            DB::setDefaultConnection('sekolah_dynamic'); // Ubah koneksi default

            // Debugging log untuk memastikan koneksi berhasil
            $currentDatabase = DB::connection()->getDatabaseName();
            // dd("Switched to database: $currentDatabase");
            logger("Switched to database: $currentDatabase"); // Log database saat ini
        } else {
            throw new \Exception('Nama database tidak disetel.');
        }
    }
}
