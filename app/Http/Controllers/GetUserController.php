<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\Request;

class GetUserController extends Controller
{
    public function index(Request $request)
    {

        $sekolahId = $request->input('sekolah_id');
        $employeeId = $request->input('employee_id');

        $data = Employee::where('sekolah_id', $sekolahId)->where('employee_id', $employeeId)->first();

        if (is_null($data)) {
            return response()->json([
                'is_correct' => false,
                'message' => 'Anda tidak terdaftar.',
            ], 404);
        }

        // Kembalikan data sebagai JSON response
        return response()->json([
            'is_correct' => true,
            'message' => 'success',
            'logo' => $data['logo'],
            'nama_pesantren' => $data['sekolah_id'],
            'photo' => $data['photo'],
            'nama' => $data['employee_name'],
            'jabatan' => $data->position->position_name,
            'majors' => $data['employee_majors_id'],
            'jam_datang' => $data['employee_jam_datang'],
            'ket_datang' => $data['employee_ket_datang'],
            'status_datang' => $data['employee_status_datang'],
            'jam_pulang' => $data['employee_jam_pulang'],
            'ket_pulang' => $data['employee_ket_pulang'],
            'status_pulang' => $data['employee_status_pulang'],
            'validasi' => $data['status_absen_temp'],
            'radius' => $data['jarak_radius'],
            'area' => [
                [
                    'lokasi' => $data['area_lokasi'],
                    'longitude' => $data['area_longitude'],
                    'latitude' => $data['area_latitude'],
                ]
            ]
        ], 200);
    }
}
