<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PresensiAreaV extends Model
{
    use HasFactory;

    protected $connection = 'sekolah';

    protected $table = 'presensi_area_v';

    protected $guarded = 'id_pegawai';
}