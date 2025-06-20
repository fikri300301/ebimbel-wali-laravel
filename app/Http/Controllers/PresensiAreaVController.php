<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PresensiAreaV;
use Illuminate\Support\Facades\Validator;

class PresensiAreaVController extends Controller
{
    public function index()
    {

        $presensi = PresensiAreaV::all();

        if (is_null($presensi)) {
            return response()->json([
                'success' => false,
                'message' => 'data belum tersedia'
            ], 404);
        }

        return response()->json([
            'data' => $presensi
        ], 200);
    }

    public function show($id_pegawai)
    {

        $presensi = PresensiAreaV::where('id_pegawai', $id_pegawai)->first();

        if (is_null($presensi)) {
            return response()->json([
                'error' => false,
                'message' => 'data tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'data' => $presensi
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nip' => 'required|numeric',
            'nama' => 'required',
            ''
        ]);
    }
}