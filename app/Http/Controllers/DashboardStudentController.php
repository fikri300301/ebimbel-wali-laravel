<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Setting;
use App\Models\Student;
use App\Models\Information;
use Illuminate\Http\Request;
use App\Models\PresensiStudent;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\setting_presensi_siswa;

class DashboardStudentController extends Controller
{
    public function index()
    {
        try {
            $user = auth()->user();
            $token = JWTAuth::parseToken();

            // Get the token payload
            $claims = $token->getPayload();

            $schoolName = $claims->get('schoolName');
            $folder = $claims->get('folder');
            // dd($user);

            $datang = PresensiStudent::where('id_student', $user->student_id)->where('jenis_absen', 'DATANG')->wheredate('date', Carbon::today())->first();
            $pulang = PresensiStudent::where('id_student', $user->student_id)->where('jenis_absen', 'PULANG')->wheredate('date', Carbon::today())->first();

            //information
            $informasi = Information::orderBy('information_input_date', 'desc')
                ->get()
                ->map(function ($info) use ($folder) {
                    return [
                        'id' => $info->information_id,
                        'title' => $info->information_title,
                        'tanggal' => Carbon::parse($info->information_input_date)->format('Y-m-d'),
                        'information_img' => "https://$folder.ebimbel.co.id/uploads/information/" . $info->information_img
                    ];
                });

            $settings = setting_presensi_siswa::all();
            $radius = setting_presensi_siswa::first()->radius;
            $namaAreas = [];
            $longi = [];
            $lati = [];
            foreach ($settings as $setting) {
                $areaDetails[] = [
                    'id_area' => $setting->area->id_area,
                    'nama_area' => $setting->area->nama_area,
                    'longi' => $setting->area->longi,
                    'lati' => $setting->area->lati,

                ];
                // $namaAreas[] = $setting->area->nama_area;
                // $longi[] = $setting->area->longi;
                // $lati[] = $setting->area->lati;
            }

            //  dd($datang);
            $data = Student::where('student_id', $user->student_id)->first();
            $whiteList = Setting::where('setting_name', 'whitelist')->first();
            $getWhiteList = $whiteList?->setting_value ?? '';
            $whiteListArray = $getWhiteList ? array_map('intval', explode(',', $getWhiteList)) : [];

            $blackList = Setting::where('setting_name', 'blacklist')->first();
            $getBlackList = $blackList?->setting_value ?? '';
            $blackListArray = $getBlackList ? array_map('intval', explode(',', $getBlackList)) : [];
            return response()->json([
                'is_correct' => true,
                'message' => 'success',
                //'whitelist' => $whiteListArray,
                //    / 'blacklist' => $blackListArray,
                'dashboard' => [
                    'sekolah' => $schoolName,
                    'foto' =>  "https://$folder.ebimbel.co.id/uploads/student/" . $data->student_img,
                    'name' => $data->student_full_name,
                    'kelas' => $data->kelas->class_name,
                    'datang' => $datang->time ?? null,
                    'pulang' => $pulang->time ?? null,
                    'radius' => $radius,
                    'area' => $areaDetails,
                    'informasi' => $informasi,
                ]
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'is_correct' => false,
                'message' => 'error',
                'data' => $th->getMessage()
            ], 500);
        }
    }

    public function logout()
    {
        JWTAuth::invalidate(JWTAuth::getToken());
        return response()->json([
            'is_correct' => true,
            'message' => 'success'
        ], 200);
    }
}
