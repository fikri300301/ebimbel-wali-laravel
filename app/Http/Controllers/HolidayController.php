<?php

namespace App\Http\Controllers;

use App\Models\holiday;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Psy\CodeCleaner\ReturnTypePass;

class HolidayController extends Controller
{
    public function index()
    {
        //$holiday = DB::connection('sekolah')->table('holiday')->get();
        $holiday = holiday::all();
        if (is_null($holiday)) {
            return response()->json([
                'success' => false,
                'message' => 'data belum tersedia'
            ], 404);
        }

        return response()->json([
            'data' => $holiday
        ], 200);
    }

    public function show($id)
    {
        $holiday = DB::connection('sekolah')->table('holiday')->where('id', $id)->first();

        if (is_null($holiday)) {
            return response()->json([
                'success' => false,
                'message' => 'data tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'data' => $holiday
        ], 200);
    }

    public function store(Request $request)
    {
        // Validasi input
        $validator = Validator::make($request->all(), [
            'year' => 'required|numeric|digits:4',
            'date' => 'required|date',
            'info' => 'required',
            'sekolah_id' => 'required'
        ]);

        // Jika validasi gagal, kembalikan response error
        if ($validator->fails()) {
            return response()->json([
                'message' => 'error',
                'error' => $validator->errors()
            ], 400);
        }

        // Insert data ke database
        $holiday_id = DB::connection('sekolah')->table('holiday')->insertGetId([
            'year' => $request->year,
            'date' => $request->date,
            'info' => $request->info,
            'sekolah_id' => $request->sekolah_id
        ]);

        // Ambil data yang baru saja dimasukkan untuk dikembalikan dalam response
        $holiday = DB::connection('sekolah')->table('holiday')->where('id', $holiday_id)->first();

        return response()->json([
            'message' => 'data berhasil dibuat',
            'data' => $holiday
        ]);
    }

    public function destroy($id)
    {

        $holiday = DB::connection('sekolah')->table('holiday')->where('id', $id)->first();

        if ($holiday) {
            DB::connection('sekolah')->table('holiday')->where('id', $id)->delete();

            return response()->json([
                'message' => 'data berhasil dihapus',
                'data' => $holiday
            ], 200);
        } else {
            return response()->json([
                'message' => 'data gagal dihapus'
            ], 404);
        }
    }

    public function update(Request $request, $id)
    {
        // Validasi input
        $validator = Validator::make($request->all(), [
            'year' => 'required|numeric|digits:4',
            'date' => 'required|date',
            'info' => 'required',
            'sekolah_id' => 'required'
        ]);

        // Jika validasi gagal, kembalikan response error
        if ($validator->fails()) {
            return response()->json([
                'message' => 'error',
                'error' => $validator->errors()
            ], 400);
        }

        // Cek apakah data holiday dengan id tertentu ada
        $holiday = DB::connection('sekolah')->table('holiday')->where('id', $id)->first();

        if (!$holiday) {
            return response()->json([
                'message' => 'Data tidak ditemukan'
            ], 404);
        }

        // Menyusun data yang akan diupdate
        $data_update = [];
        if ($request->filled('year')) {
            $data_update['year'] = $request->year;
        }

        if ($request->filled('date')) {
            $data_update['date'] = $request->date;  // Perbaiki: Ganti $request->year menjadi $request->date
        }

        if ($request->filled('info')) {
            $data_update['info'] = $request->info;  // Perbaiki: Ganti $request->year menjadi $request->info
        }

        if ($request->filled('sekolah_id')) {
            $data_update['sekolah_id'] = $request->sekolah_id;  // Perbaiki: Ganti $request->year menjadi $request->sekolah_id
        }

        // Update data di database
        DB::connection('sekolah')->table('holiday')->where('id', $id)->update($data_update);

        // Mengembalikan response setelah data diperbarui
        return response()->json([
            'message' => 'Data berhasil diperbarui',
            'data' => DB::connection('sekolah')->table('holiday')->where('id', $id)->first()
        ]);
    }
}
