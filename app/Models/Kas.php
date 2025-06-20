<?php

namespace App\Models;

use Carbon\Carbon;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Kas extends Model
{
    use HasFactory;

    protected $table = 'kas';
    protected $guarded = ['kas_id'];
    protected $primaryKey = 'kas_id';
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

    public static function getNoref($like, $idMajors)
    {
        // Query untuk mendapatkan nomor referensi terakhir
        $query = Kas::selectRaw('MAX(RIGHT(kas_noref, 2)) AS no_max')
            ->whereDate('kas_input_date', Carbon::today())
            ->where('kas_majors_id', $idMajors)
            ->where('kas_noref', 'like', $like . '%')
            ->where('kas_category', '1')
            ->first();

        // Menghitung nomor referensi baru
        if ($query && $query->no_max) {
            $tmp = (int)$query->no_max + 1;
            $noref = sprintf('%02d', $tmp);  // Format 2 digit
        } else {
            $noref = '01';  // Jika tidak ada data, mulai dari '01'
        }

        // Mengembalikan nomor referensi dengan format 'ddmmyy' + nomor urut
        return Carbon::now()->format('dmy') . $noref;
    }
}
