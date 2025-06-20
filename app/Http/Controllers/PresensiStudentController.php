<?php

namespace App\Http\Controllers;

use DateTime;
use Exception;
use Carbon\Carbon;
use App\Models\day;
use App\Models\DataWaktu;
use App\Models\AreaAbsensi;
use Illuminate\Http\Request;
use App\Models\DataWaktuSiswa;
use App\Models\PresensiStudent;
use Dflydev\DotAccessData\Data;
use App\Services\WhatsappService;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\setting_presensi_siswa;
use Illuminate\Support\Facades\Validator;

class PresensiStudentController extends Controller

{
    // private function compressImage($image, $destinationPath, $fileName, $compressionQuality)
    // {
    //     if ($compressionQuality < 0) {
    //         return;
    //     }
    //     imagejpeg($image, $destinationPath . '/' . $fileName, $compressionQuality);

    //     if (filesize($destinationPath . '/' . $fileName) > 150000) {
    //         return $this->compressImage($image, $destinationPath, $fileName, $compressionQuality - 5);
    //     }


    //     // Hapus resource gambar dari memori
    //     return imagedestroy($image);
    // }
    private function compressImage($image, $destinationPath, $fileName, $compressionQuality = 50)
    {
        // Dapatkan dimensi asli
        $originalWidth = imagesx($image);
        $originalHeight = imagesy($image);

        // Set dimensi maksimum (ini akan mengurangi ukuran file dengan signifikan)
        $maxWidth = 1024;
        $maxHeight = 1024;

        // Hitung rasio pengubahan ukuran
        $ratio = min(
            ($maxWidth > 0 ? $maxWidth / $originalWidth : 1),
            ($maxHeight > 0 ? $maxHeight / $originalHeight : 1)
        );

        // Jika gambar lebih kecil dari ukuran maksimum, tetap gunakan ukuran asli
        if ($ratio > 1) {
            $ratio = 1;
        }

        // Hitung dimensi baru
        $newWidth = round($originalWidth * $ratio);
        $newHeight = round($originalHeight * $ratio);

        // Buat gambar baru dengan dimensi yang diubah
        $resizedImage = imagecreatetruecolor($newWidth, $newHeight);

        // Resize gambar
        imagecopyresampled(
            $resizedImage,
            $image,
            0,
            0,
            0,
            0,
            $newWidth,
            $newHeight,
            $originalWidth,
            $originalHeight
        );

        // Simpan gambar dengan kompresi iteratif (bukan rekursif)
        $filePath = $destinationPath . '/' . $fileName;
        $quality = $compressionQuality;
        $maxFileSize = 150000; // 150 KB
        $minQuality = 10;      // Kualitas minimum

        // Loop untuk menemukan kualitas kompresi optimal
        while ($quality >= $minQuality) {
            // Simpan gambar dengan kualitas saat ini
            imagejpeg($resizedImage, $filePath, $quality);

            // Cek ukuran file
            $fileSize = filesize($filePath);

            // Jika ukuran file sudah cukup kecil, keluar
            if ($fileSize <= $maxFileSize) {
                break;
            }

            // Kurangi kualitas dan coba lagi
            $quality -= 5;
        }

        // Hapus resource gambar dari memori
        imagedestroy($image);
        imagedestroy($resizedImage);

        return $filePath;
    }

    public function store(Request $request)
    {
        //dd('COBA');
        try {
            $user = auth()->user();
            $token = JWTAuth::parseToken();

            // Get the token payload
            $claims = $token->getPayload();

            $waktu = $claims->get('waktu_idonesia');

            $schoolName = $claims->get('schoolName');
            $folder = $claims->get('folder');
            $areaAbsen = setting_presensi_siswa::first();
            $date = Carbon::now()->format('Y-m-d');
            $time = Carbon::now($waktu)->format('H:i:s');
            //dd($time);
            $validator = Validator::make($request->all(), [
                'id_student' => 'nullable',
                'id_class' => 'nullable',
                'id_area_absensi' => 'nullable',
                'jenis_absen' => 'nullable',
                'status' => 'nullable',
                'date' => 'nullable',
                'time' => 'nullable',
                'longi' => 'nullable',
                'lati' => 'nullable',
                'note' => 'nullable',
                'catatan_absen' => 'nullable', //kurang
                'foto' => 'nullable|' . ($request->hasFile('foto') ? 'image|mimes:jpeg,png,jpg,gif' : 'string'), //kurang
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'is_correct' => false,
                    'message' => $validator->errors()
                ], 400);
            }

            $daysInDatabase = [
                1 => 'Ahad', // 1 = Ahad
                2 => 'Senin',
                3 => 'Selasa',
                4 => 'Rabu',
                5 => 'Kamis',
                6 => 'Jumat',
                7 => 'Sabtu',
            ];
            Carbon::setLocale('id');
            $today = Carbon::now();

            $namaHari = $today->isoFormat('dddd');
            $getDayId = day::where('day_name', $namaHari)->first();
            $dayId = $getDayId->day_id;

            $get_data_waktu = DataWaktuSiswa::where('data_waktu_majors_id', $user->majors_majors_id)->where('data_waktu_day_id', $dayId)->first();
            //ini nanti diganti data waktu siswa
            if ($get_data_waktu == null) {
                throw new \Exception("Waktu datang belum diatur hubungi admin");
            }

            $limitTime = $get_data_waktu->data_waktu_masuk;
            $dateTime = new DateTime($limitTime);
            $dateTime->modify('-30 minutes');
            $prepare = $dateTime->format('H:i:s');

            $status = '';
            if ($limitTime > $time) {
                $status = 'Tepat Waktu';
                $keteranganKeterlambatan = '';
            } else {
                $status = 'Terlambat';
                $selisihJam = Carbon::parse($time)->diffInHours(Carbon::parse($limitTime));
                $selisihMenit = Carbon::parse($time)->diffInMinutes(Carbon::parse($limitTime)) % 60;

                // Buat pesan keterlambatan
                $keteranganKeterlambatan = 'Anda terlambat ' . $selisihJam . ' jam ' . $selisihMenit . ' menit.';

                // dd($keteranganKeterlambatan);
            }

            // dd($noWa);
            $absenPadaTanggalIni = PresensiStudent::where('id_student', $user->student_id)
                ->where('date', $date)
                ->whereIN('jenis_absen', ['DATANG', 'PULANG', 'IZIN'])
                ->first();

            if ($absenPadaTanggalIni) {
                throw new Exception('Anda sudah melakukan presensi pada tanggal ini');
            }

            $data = [
                'id_student' => $user->student_id,
                'id_class' => $user->class_class_id,
                'id_area_absensi' => $areaAbsen->id_area_absensi,
                'jenis_absen' => 'DATANG',
                'status' => $status,
                'date' => $date,
                'time' => $time,
                'catatan_absen' => $request->catatan_absen,
                'foto' => $request->foto,
                'longi' => $areaAbsen->area->longi,
                'lati' => $areaAbsen->area->lati,
                'note' => $request->note,
            ];

            if ($request->hasFile('foto')) {
                $file = $request->file('foto');
                $fileName = time() . '.' . $file->getClientOriginalExtension();
                $uploadDirectory = "../../$folder.ebimbel.co.id/uploads/absensi-student";
                if (!is_dir($uploadDirectory)) {
                    mkdir($uploadDirectory, 0777, true);
                }
                // dd($destinationPath);
                $file->move($uploadDirectory, $fileName);

                // Construct the full path for database storage
                $fullPath = 'absensi/' . $fileName;

                // Update the data array with the new file path
                $data['foto'] = $fullPath;
            } elseif ($request->input('foto')) {
                try {
                    $rawBase64 = $request->input('foto');

                    // Cek apakah input berisi header data:image atau hanya string base64
                    if (strpos($rawBase64, 'data:image/') === 0) {
                        // Format lengkap dengan header
                        if (!preg_match('/^data:image\/(jpeg|png|jpg|gif);base64,/', $rawBase64)) {
                            return response()->json(['message' => 'Format Base64 tidak valid'], 400);
                        }

                        [$mimePart, $encodedData] = explode(';base64,', $rawBase64);
                        $extension = str_replace('data:image/', '', $mimePart);
                    } else {
                        // Hanya string base64 tanpa header
                        $encodedData = $rawBase64;
                        $extension = 'jpeg'; // Gunakan default extension
                    }

                    // Validasi tipe gambar
                    if (!in_array($extension, ['jpeg', 'jpg', 'png', 'gif'])) {
                        return response()->json(['message' => 'Tipe gambar tidak valid'], 400);
                    }

                    // Decode Base64 data
                    $imageData = base64_decode($encodedData);
                    if ($imageData === false) {
                        return response()->json(['message' => 'Data Base64 tidak valid'], 400);
                    }

                    // Buat gambar dari string
                    $image = imagecreatefromstring($imageData);
                    if ($image === false) {
                        return response()->json(['message' => 'Gagal membuat gambar dari data Base64'], 500);
                    }

                    // Kompres gambar
                    $fileName = md5($encodedData) . '.' . $extension;
                    // atau
                    $fileName = substr(md5($encodedData . time()), 0, 10) . '.' . $extension;
                    $destinationPath = public_path("../../$folder.ebimbel.co.id/uploads/absensi-student");

                    if (!is_dir($destinationPath)) {
                        mkdir($destinationPath, 0777, true);
                    }

                    // Gunakan fungsi compressImage yang telah diperbaiki
                    $this->compressImage($image, $destinationPath, $fileName);

                    // Simpan nama file di database
                    $fullPath = 'absensi/' . $fileName;
                    dd($fullPath);
                    $data['foto'] = $fullPath; //perbaikan disibi

                    // $rawBase64 = $request->input('foto');
                    // $extension = 'jpeg';
                    // $base64Image = "data:image/{$extension};base64," . $rawBase64;

                    // // Validasi format Base64
                    // if (!preg_match('/^data:image\/(jpeg|png|jpg|gif);base64,/', $base64Image)) {
                    //     return response()->json(['message' => 'Invalid Base64 format'], 400);
                    // }

                    // [$mimePart, $encodedData] = explode(';base64,', $base64Image);
                    // $extension = str_replace('data:image/', '', $mimePart);

                    // // Validasi tipe gambar
                    // if (!in_array($extension, ['jpeg', 'jpg', 'png', 'gif'])) {
                    //     return response()->json(['message' => 'Invalid image type'], 400);
                    // }

                    // // Decode Base64 data
                    // $imageData = base64_decode($encodedData);

                    // // Buat gambar dari string
                    // $image = imagecreatefromstring($imageData);


                    // if ($image === false) {
                    //     return response()->json(['message' => 'Failed to create image from Base64 data'], 500);
                    // }

                    // // Kompres gambar
                    // $fileName = uniqid() . '.' . $extension;
                    // $destinationPath = public_path("../../$folder.adminsekolah.net/uploads/absensi-student");

                    // if (!is_dir($destinationPath)) {
                    //     mkdir($destinationPath, 0777, true);
                    // }

                    // $compressionQuality = 50; // Kualitas kompresi (0-100)


                    // $this->compressImage($image, $destinationPath, $fileName, $compressionQuality);
                    // // Simpan gambar berdasarkan tipe


                    // // Simpan nama file di database
                    // $fullPath = 'absensi/' . $fileName;
                    // //dd($fullPath);
                    // $data['foto'] = $fullPath;


                } catch (\Exception $e) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Error saat memproses gambar: ' . $e->getMessage()
                    ], 500);
                }
            }

            //insert ke table presensi_student
            //    $data = DB::table('presensi_student')->insert($data);
            $data = PresensiStudent::create($data);
            $whatsappService = new WhatsappService();
            $noWa = $user->student_parent_phone;

            $pesan = "Report Presensi " . Carbon::now()->format('d F Y') . "\n";
            $pesan .= "==================================\n";
            $pesan .= "Nama : " . $user->student_full_name . "\n";
            //    $pesan .= "Prepare : 07:30:00-08:00:00\n";
            $pesan .= "Prepare :" . $prepare . " - " . $limitTime . "\n";
            $pesan .= "Presensi Masuk : " . $time . " ($status)\n";

            if ($status === 'Tepat Waktu') {
                $pesan .= "Terima kasih sudah datang tepat waktu. Semoga harimu menyenangkan. Tapi tolong besok datang lebih awal untuk prepare ya.";
            } else {
                $pesan .= "Anda terlambat pada hari ini.\n";
                $pesan .= "$keteranganKeterlambatan\n";
                $pesan .= "keterangan " . "$request->catatan_absen\n";
                $pesan .= ". Ayo lebih giat lagi. Tolong besok datang lebih awal lagi ya.";
            }

            $whatsappService->kirimPesan($noWa, $pesan);
            return response()->json([
                'is_correct' => true,
                'message' => 'success',
                //  'data' => $data
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'is_correct' => false,
                'message' => $th->getMessage(),
                // 'line' => $th->getLine()
            ], 400);
        }
    }

    public function storePulang(Request $request)
    {
        try {
            $user = auth()->user();
            $token = JWTAuth::parseToken();

            // Get the token payload
            $claims = $token->getPayload();

            $waktu = $claims->get('waktu_idonesia');

            $schoolName = $claims->get('schoolName');
            $folder = $claims->get('folder');
            $areaAbsen = setting_presensi_siswa::first();
            $date = Carbon::now()->format('Y-m-d');
            $time = Carbon::now($waktu)->format('H:i:s');
            //dd($time);
            $validator = Validator::make($request->all(), [
                'id_student' => 'nullable',
                'id_class' => 'nullable',
                'id_area_absensi' => 'nullable',
                'jenis_absen' => 'nullable',
                'status' => 'nullable',
                'date' => 'nullable',
                'time' => 'nullable',
                'longi' => 'nullable',
                'lati' => 'nullable',
                'note' => 'nullable',
                'foto' => 'nullable|' . ($request->hasFile('foto') ? 'image|mimes:jpeg,png,jpg,gif' : 'string'),
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'is_correct' => false,
                    'message' => $validator->errors()
                ], 400);
            }

            $daysInDatabase = [
                1 => 'Ahad', // 1 = Ahad
                2 => 'Senin',
                3 => 'Selasa',
                4 => 'Rabu',
                5 => 'Kamis',
                6 => 'Jumat',
                7 => 'Sabtu',
            ];
            Carbon::setLocale('id');
            $today = Carbon::now();

            $namaHari = $today->isoFormat('dddd');
            $getDayId = day::where('day_name', $namaHari)->first();
            $dayId = $getDayId->day_id;

            $get_data_waktu = DataWaktuSiswa::where('data_waktu_majors_id',  $user->majors_majors_id)->where('data_waktu_day_id', $dayId)->first(); //ini nanti diganti data waktu siswa
            $limitTime = $get_data_waktu->data_waktu_pulang;
            $dateTime = new DateTime($limitTime);
            $dateTime->modify('+30 minutes');
            $prepare = $dateTime->format('H:i:s');

            $status = '';
            if ($time < $limitTime) {
                $status = 'Pulang Cepat';

                //hitung pulang cepat
                $selisihJam = Carbon::parse($limitTime)->diffInHours(Carbon::parse($time));
                $selisihMenit = Carbon::parse($limitTime)->diffInMinutes(Carbon::parse($time)) % 60;

                $keteranganPulangCepat = 'Anda pulang lebih cepat ' . $selisihJam . ' jam ' . $selisihMenit . ' menit.';
            } else {
                $status = 'Tepat Waktu';
                $keteranganPulangCepat = '';
            }

            $data = [
                'id_student' => $user->student_id,
                'id_class' => $user->class_class_id,
                'id_area_absensi' => $areaAbsen->id_area_absensi,
                'jenis_absen' => 'PULANG',
                'status' => $status,
                'date' => $date,
                'time' => $time,
                'longi' => $areaAbsen->area->longi,
                'lati' => $areaAbsen->area->lati,
                'foto' => $request->foto,
                'note' => $request->note,
            ];
            if ($request->hasFile('foto')) {
                $file = $request->file('foto');
                $fileName = time() . '.' . $file->getClientOriginalExtension();
                $uploadDirectory = "../../$folder.ebimbel.co.id/uploads/absensi-student";
                if (!is_dir($uploadDirectory)) {
                    mkdir($uploadDirectory, 0777, true);
                }
                // dd($destinationPath);
                $file->move($uploadDirectory, $fileName);

                // Construct the full path for database storage
                $fullPath = 'absensi/' . $fileName;
                // dd($fullPath);
                // Update the data array with the new file path
                $data['foto'] = $fullPath;
            } elseif ($request->input('foto')) {

                $rawBase64 = $request->input('foto');
                $extension = 'jpeg';
                $base64Image = "data:image/{$extension};base64," . $rawBase64;

                // Validasi format Base64
                if (!preg_match('/^data:image\/(jpeg|png|jpg|gif);base64,/', $base64Image)) {
                    return response()->json(['message' => 'Invalid Base64 format'], 400);
                }

                [$mimePart, $encodedData] = explode(';base64,', $base64Image);
                $extension = str_replace('data:image/', '', $mimePart);

                // Validasi tipe gambar
                if (!in_array($extension, ['jpeg', 'jpg', 'png', 'gif'])) {
                    return response()->json(['message' => 'Invalid image type'], 400);
                }

                // Decode Base64 data
                $imageData = base64_decode($encodedData);

                // Buat gambar dari string
                $image = imagecreatefromstring($imageData);


                if ($image === false) {
                    return response()->json(['message' => 'Failed to create image from Base64 data'], 500);
                }

                // Kompres gambar
                $fileName = uniqid() . '.' . $extension;
                $destinationPath = public_path("../../$folder.ebimbel.co.id/uploads/absensi-student");

                if (!is_dir($destinationPath)) {
                    mkdir($destinationPath, 0777, true);
                }

                $compressionQuality = 50; // Kualitas kompresi (0-100)


                $this->compressImage($image, $destinationPath, $fileName, $compressionQuality);
                // Simpan gambar berdasarkan tipe


                // Simpan nama file di database
                $fullPath = 'absensi/' . $fileName;
                //dd($fullPath);
                $data['foto'] = 0; //perbaikan disini
            }
            //insert ke table presensi_student
            $data = DB::table('presensi_student')->insert($data);
            $whatsappService = new WhatsappService();
            $noWa = $user->student_parent_phone;

            $pesan = "Report Presensi " . Carbon::now()->format('d F Y') . "\n";
            $pesan .= "==================================\n";
            $pesan .= "Nama : " . $user->student_full_name . "\n";
            if ($status === 'Pulang Cepat') {
                $pesan .= $status . "\n";
                $pesan .= "$keteranganPulangCepat\n";
                $pesan .= "keterangan " . "$request->catatan_absen\n";
                $pesan .= ". Terima kasih sudah absen pulang lebih cepat. Semoga harimu menyenangkan.";
            } else {
                $pesan .= "Presensi Pulang : " . $time . " ($status)\n";
                $pesan .= "Prepare :" . $limitTime . " - " . $prepare . "\n";
            }
            $whatsappService->kirimPesan($noWa, $pesan);
            return response()->json([
                'is_correct' => true,
                'message' => 'success',
                //  'data' => $data
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'is_correct' => false,
                'message' => $th->getMessage()
            ], 400);
        }
    }

    public function storeIzin(Request $request)
    {
        try {
            $user = auth()->user();
            $token = JWTAuth::parseToken();

            // Get the token payload
            $claims = $token->getPayload();

            $waktu = $claims->get('waktu_idonesia');

            $schoolName = $claims->get('schoolName');
            $folder = $claims->get('folder');
            $areaAbsen = setting_presensi_siswa::first();
            $date = Carbon::now()->format('Y-m-d');
            $time = Carbon::now($waktu)->format('H:i:s');
            //dd($time);
            $validator = Validator::make($request->all(), [
                'id_student' => 'nullable',
                'id_class' => 'nullable',
                'jenis_absen' => 'required|in:IZIN,SAKIT,ALPHA',
                'status' => 'nullable',
                'date' => 'nullable',
                'time' => 'nullable',
                'longi' => 'nullable',
                'lati' => 'nullable',
                'note' => 'nullable',
                'foto' => 'nullable|' . ($request->hasFile('foto') ? 'image|mimes:jpeg,png,jpg,gif' : 'string'),
                'tanggal_awal' => 'required|date',
                'tanggal_akhir' => 'required|date|after_or_equal:tanggal_awal',
            ]);

            $tanggal1 = $request->tanggal_awal;

            if ($validator->fails()) {
                return response()->json([
                    'is_correct' => false,
                    'message' => $validator->errors()
                ], 400);
            }

            $daysInDatabase = [
                1 => 'Ahad', // 1 = Ahad
                2 => 'Senin',
                3 => 'Selasa',
                4 => 'Rabu',
                5 => 'Kamis',
                6 => 'Jumat',
                7 => 'Sabtu',
            ];
            Carbon::setLocale('id');
            $today = Carbon::now();
            $dayId = $today->dayOfWeek + 1;



            if ($request->hasFile('foto')) {
                $file = $request->file('foto');
                $fileName = time() . '.' . $file->getClientOriginalExtension();
                $uploadDirectory = "../../$folder.ebimbel.co.id/uploads/izin-student";
                if (!is_dir($uploadDirectory)) {
                    mkdir($uploadDirectory, 0777, true);
                }
                // dd($destinationPath);
                $file->move($uploadDirectory, $fileName);

                // Construct the full path for database storage
                $fullPath = 'izin/' . $fileName;
                // dd($fullPath);
                // Update the data array with the new file path
                $data['foto'] = $fullPath;
            } elseif ($request->input('foto')) {

                $rawBase64 = $request->input('foto');
                $extension = 'jpeg';
                $base64Image = "data:image/{$extension};base64," . $rawBase64;

                // Validasi format Base64
                if (!preg_match('/^data:image\/(jpeg|png|jpg|gif);base64,/', $base64Image)) {
                    return response()->json(['message' => 'Invalid Base64 format'], 400);
                }

                [$mimePart, $encodedData] = explode(';base64,', $base64Image);
                $extension = str_replace('data:image/', '', $mimePart);

                // Validasi tipe gambar
                if (!in_array($extension, ['jpeg', 'jpg', 'png', 'gif'])) {
                    return response()->json(['message' => 'Invalid image type'], 400);
                }

                // Decode Base64 data
                $imageData = base64_decode($encodedData);

                // Buat gambar dari string
                $image = imagecreatefromstring($imageData);


                if ($image === false) {
                    return response()->json(['message' => 'Failed to create image from Base64 data'], 500);
                }

                // Kompres gambar
                $fileName = uniqid() . '.' . $extension;
                $destinationPath = public_path("../../$folder.ebimbel.co.id/uploads/izin-student");

                if (!is_dir($destinationPath)) {
                    mkdir($destinationPath, 0777, true);
                }

                $compressionQuality = 50; // Kualitas kompresi (0-100)


                $this->compressImage($image, $destinationPath, $fileName, $compressionQuality);
                // Simpan gambar berdasarkan tipe


                // Simpan nama file di database
                $fullPath = 'izin/' . $fileName;
                //dd($fullPath);
                $data['foto'] = $fullPath;
            }


            // $data = [
            //     'id_student' => $user->student_id,
            //     'id_class' => $user->class_class_id,
            //     'id_area_absensi' => $areaAbsen->id_area_absensi,
            //     'jenis_absen' => $request->jenis_absen,
            //     'status' => (NULL),
            //     'date' => $date,
            //     'time' => $time,
            //     'catatan_absen' => $request->catatan_absen,
            //     'foto' => $data['foto'],
            //     'longi' => (NULL),
            //     'lati' => (NULL),
            //     'note' => $request->note,
            // ];

            $startDate = Carbon::parse($request->tanggal_awal);
            $endDate = Carbon::parse($request->tanggal_akhir);
            $dateRange = [];

            // Create array of all dates in the range
            for ($date = $startDate; $date->lte($endDate); $date->addDay()) {
                $dateRange[] = $date->format('Y-m-d');
            }

            $createdRecords = [];
            $skippedDates = [];

            // Process each date in the range
            foreach ($dateRange as $date) {
                // Check if student already has attendance record for this date
                $existingAbsen = PresensiStudent::where('id_student', $user->student_id)
                    ->where('date', $date)
                    ->whereIn('jenis_absen', ['DATANG', 'PULANG', 'IZIN'])
                    ->first();

                if ($existingAbsen) {
                    $skippedDates[] = $date;
                    continue; // Skip this date and move to the next one
                }

                // Create attendance record for this date
                $data = [
                    'id_student' => $user->student_id,
                    'id_class' => $user->class_class_id,
                    'id_area_absensi' => $areaAbsen->id_area_absensi,
                    'jenis_absen' => $request->jenis_absen,
                    'status' => null,
                    'date' => $date,
                    //'time' => $time,
                    'time' => $time,
                    'catatan_absen' => $request->catatan_absen,
                    'foto' => $data['foto'],
                    'longi' => null,
                    'lati' => null,
                    'note' => $request->note,
                ];

                $record = PresensiStudent::create($data);
                $createdRecords[] = $record;
            }

            // Prepare response message
            $message = 'Berhasil menambahkan izin';
            // $data = PresensiStudent::create($data);

            return response()->json([
                'is_correct' => true,
                'message' => 'success',
                //  'data' => $data
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'is_correct' => false,
                'message' => $th->getMessage()
            ], 400);
        }
    }
}
