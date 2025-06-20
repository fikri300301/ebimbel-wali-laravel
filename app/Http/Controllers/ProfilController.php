<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Student;
use App\Models\Employee;
use Illuminate\Http\Request;
use App\Services\DatabaseSwitcher;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;


class ProfilController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/auth/profil",
     *     summary="Get data profil",
     *     description="Mengambil data profil",
     *     tags={"Profil"},
     *     security={{"BearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Data Profil berhasil diambil",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="foto", type="string", example="foto.jpeg"),
     *                     @OA\Property(property="sekolah", type="string", example="nama sekolah"),
     *                     @OA\Property(property="nama", type="integer", example="okta"),
     *                     @OA\Property(property="Nip", type="string", example="1222"),
     *                     @OA\Property(property="jabatan", type="string", example="guru"),
     *                     @OA\Property(property="email", type="string", example="okta@gmail.com"),
     *                     @OA\Property(property="no_ponsel", type="string", example="087262662"),
     *                     @OA\Property(property="tempat_lahir", type="date", example="2024-10-12"),
     *                     @OA\Property(property="tanggal_lahir", type="date", example="2024-10-12"),
     *                     @OA\Property(property="alamat", type="string", example="Jl kh hasyim asyari"),
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="null",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="error")
     *         )
     *     )
     * )
     */
    // protected $databaseSwitcher;

    // // Inject DatabaseSwitcher di constructor
    // public function __construct(DatabaseSwitcher $databaseSwitcher)
    // {
    //     $this->databaseSwitcher = $databaseSwitcher;
    // }

    private function compressImage($image, $destinationPath, $fileName, $compressionQuality)
    {
        if ($compressionQuality < 0) {
            return;
        }
        imagejpeg($image, $destinationPath . '/' . $fileName, $compressionQuality);

        if (filesize($destinationPath . '/' . $fileName) > 150000) {
            return $this->compressImage($image, $destinationPath, $fileName, $compressionQuality - 5);
        }


        // Hapus resource gambar dari memori
        return imagedestroy($image);
    }
    public function index()
    {
        // dd('test');
        $token = JWTAuth::parseToken();
        // dd($token);
        // Get the token payload
        $claims = $token->getPayload();
        $location = $claims->get('location');
        $domain = $claims->get('folder');
        // Extract the `schoolName` claim from the payload
        $schoolName = $claims->get('schoolName');
        $data = auth()->user();
        //$photoUrl = asset($data->student_img);
        $fileName = $data->student_img;
        //  dd($data);
        return response()->json([
            'is_correct' => true,
            'message' => 'success',
            'profil' => [
                'sekolah' => $schoolName,
                'nama' => $data->student_full_name,
                'nis' => $data->student_nis,
                'kelas' => $data->kelas->class_name,
                'unit' => $data->major->majors_name,
                'kamar' => $data->madin->madin_name ?? null,
                //  'photo' => $photoUrl,
                'foto' =>  "https://" . $domain . ".ebimbel.co.id/uploads/student/" . $fileName, // perbaikan
                //'photo' =>  Storage::url($data->student_img),
                'tempat_lahir' => $data->student_born_place,
                'tanggal_lahir' => $data->student_born_date,
                'jenis_kelamin' => $data->student_gender,
                'alamat' => $data->student_address,
                'nama_ayah' => $data->student_name_of_father,
                'nama_ibu' => $data->student_name_of_mother,
                'no_wa' => $data->student_parent_phone
            ]
        ]);
    }


    /**
     * @OA\Post(
     *     path="/api/auth/profil",
     *     summary="Update Profil",
     *     description="Memperbarui sebagian data profil",
     *     tags={"Profil"},
     *     security={{"BearerAuth": {}}},
     *         @OA\RequestBody(
     *         required=true,
     *         content={
     *             @OA\MediaType(
     *                 mediaType="multipart/form-data",
     *                 @OA\Schema(
     *                     required={"employee_name", "employee_email","employee_phone","employee_born_place","employee_photo","employee_born_date","employee_address"},
     *                     @OA\Property(property="employee_name", type="string", description="Nama karyawan",example="fikri pratam"),
     *                     @OA\Property(property="employee_email", type="string", format="email", description="Email karyawan" ,example="fikri@gmail.com"),
     *                     @OA\Property(property="employee_phone", type="string", description="Nomor telepon karyawan",example="089727236"),
     *                     @OA\Property(property="employee_born_place", type="string", description="Tempat lahir karyawan", example="kediri"),
     *                     @OA\Property(property="employee_photo", type="string", format="binary", description="Foto karyawan"),
     *                     @OA\Property(property="employee_born_date", type="string", format="date", description="Tanggal lahir karyawan (YYYY-MM-DD)",  example="2024-10-12"),
     *                     @OA\Property(property="employee_address", type="string", description="Alamat karyawan", example="jl merdeka"),
     *                 )
     *             )
     *         }
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Data berhasil diupdate",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Data berhasil diupdate")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Data tidak ditemukan",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="data tidak ditemukan")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validasi gagal",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation error"),
     *         )
     *     )
     * )
     */
    function getUploadDirectory($domain, $location)
    {
        $defaultDirectory = "../../../$domain/uploads/student/";


        if ($domain == 'demo') {

            // return "../public_html/$domain/uploads/prove/";  // Untuk domain demo

            return "../../public_html/$domain/uploads/student/";
        }
        if ($domain == "alrisalah") {

            return "../../../alrisalah2/uploads/student/"; // Untuk domain alrisalah
        }

        switch ($location) {
            case '1':
                return "../../public_html/backup/$domain/uploads/student/";
            case '2':
                return "../../public_html/$domain/uploads/student/";
            case '3':
                return "../../$domain.adminsekolah.net/uploads/student/";
            default:
                return $defaultDirectory;
        }
    }

    public function update1(Request $request)
    {
        $student = auth()->user();
        $token = JWTAuth::parseToken();

        // Get the token payload
        $claims = $token->getPayload();

        $location = $claims->get('location');
        $domain = $claims->get('folder');
        // Validasi data
        $validator = Validator::make($request->all(), [
            'student_full_name' => 'required|string',
            'student_born_place' => 'string',
            'student_born_date' => 'date',
            'student_gender' => 'required|in:L,P',
            'student_address' => 'string',
            'student_parent_phone' => 'numeric',
            'student_name_of_mother' => 'string',
            'student_name_of_father' => 'string',
            'student_img' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // Validasi gambar
        ], [
            'student_full_name.required' => 'Nama lengkap siswa wajib diisi.',
            'student_gender.in' => 'Jenis kelamin harus salah satu dari pilihan: L (Laki-laki), P (Perempuan).'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => implode(', ', $validator->errors()->all())
            ], 400);
        }
        // Ambil data untuk update
        $data_update = $request->only([
            'student_full_name',
            'student_born_place',
            'student_born_date',
            'student_gender',
            'student_address',
            'student_parent_phone',
            'student_name_of_mother',
            'student_name_of_father'
        ]);

        // Mengatur waktu dengan Carbon
        Carbon::setLocale('id');
        $waktu = Carbon::now()->translatedFormat('Y-m-d H:i:s'); // Menggunakan timezone default aplikasi
        $data_update['student_input_date'] = $waktu;
        $data_update['student_last_update'] = $waktu;

        $filePath = null;
        $imageUrl = null;

        // Tentukan direktori upload sesuai dengan fungsi getUploadDirectory
        $uploadDirectory = $this->getUploadDirectory($domain, $location);
        // Proses upload gambar
        if ($request->hasFile('student_img') && $request->file('student_img')->isValid()) {
            $file = $request->file('student_img');
            $filePath = $file->getPathname();
            $fileName = $file->getClientOriginalName();

            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
            $extension = $file->getClientOriginalExtension();

            if (!in_array(strtolower($extension), $allowedExtensions)) {
                return response()->json(['message' => 'Invalid image type'], 400);
            }

            $imageName = uniqid() . '.' . $extension;

            // Tentukan folder tujuan berdasarkan fungsi getUploadDirectory
            $destinationPath = $uploadDirectory;

            // Pastikan folder tujuan ada, jika tidak buat folder
            if (!is_dir($destinationPath)) {
                mkdir($destinationPath, 0777, true);
            }

            $fileNameWithTime =  time() . "_" . $fileName;

            // Pindahkan file ke folder yang sesuai
            move_uploaded_file($filePath, $destinationPath  . $fileNameWithTime);

            // Cek apakah file berhasil dipindahkan
            if (!file_exists($destinationPath  . $fileNameWithTime)) {
                return response()->json(['message' => 'Failed to move the image'], 500);
            }

            // Hanya simpan nama file di database
            $filePath = $fileNameWithTime;
        } else {
            // Jika input berupa Base64
            $base64Image = $request->input('student_img');

            // Tambahkan prefix jika tidak ada
            if (!str_starts_with($base64Image, 'data:image/')) {
                $base64Image = 'data:image/png;base64,' . $base64Image; // Default ke 'image/png'
            }

            // Pisahkan MIME type dan data Base64
            if (!str_contains($base64Image, ';base64,')) {
                return response()->json(['message' => 'Invalid Base64 format'], 400);
            }
            [$mimePart, $data] = explode(';base64,', $base64Image);

            // Validasi MIME type
            if (!str_starts_with($mimePart, 'data:image/')) {
                return response()->json(['message' => 'Invalid MIME type'], 400);
            }

            $mimeType = str_replace('data:image/', '', $mimePart);
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];

            if (!in_array($mimeType, $allowedExtensions)) {
                return response()->json(['message' => 'Invalid image type'], 400);
            }

            // Decode Base64
            $decodedImage = base64_decode($data);
            if ($decodedImage === false) {
                return response()->json(['message' => 'Base64 decode failed'], 400);
            }

            // Tentukan nama file
            $fileName = uniqid() . '.' . $mimeType; // Generate nama file unik
            $publicPath = public_path($uploadDirectory);

            // Tentukan path lengkap untuk gambar
            $filePath = $fileName;

            // Simpan gambar langsung di folder yang sesuai
            if (file_put_contents($publicPath . '/' . $filePath, $decodedImage) === false) {
                return response()->json(['message' => 'Failed to save image'], 500);
            }

            // Dapatkan URL untuk gambar Base64
            $imageUrl = asset($uploadDirectory . '/' . $filePath);
        }


        return response()->json([
            'is_correct' => false,
            'message' => 'Update failed'
        ], 500);
    }

    public function update(Request $request)
    {
        $student = auth()->user();
        $token = JWTAuth::parseToken();

        // Get the token payload
        $claims = $token->getPayload();
        $location = $claims->get('location');
        $domain = $claims->get('folder');


        // Validasi data
        $validator = Validator::make($request->all(), [
            'student_full_name' => 'nullable',
            'student_born_place' => 'nullable',
            'student_born_date' => 'nullable|date',
            'student_gender' => 'in:L,P',
            'student_address' => 'nullable',
            'student_parent_phone' => 'nullable',
            'student_name_of_mother' => 'nullable',
            'student_name_of_father' => 'nullable',
            'student_img' => ($request->hasFile('student_img') ? 'nullable|image|mimes:jpeg,png,jpg,gif' : 'string'), // Validasi gambar
        ], [
            'student_full_name.required' => 'Nama lengkap siswa wajib diisi.',
            'student_gender.in' => 'Jenis kelamin harus salah satu dari pilihan: L (Laki-laki), P (Perempuan).'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => implode(', ', $validator->errors()->all())
            ], 400);
        }

        // Ambil data untuk update
        $data_update = $request->only([
            'student_full_name',
            'student_born_place',
            'student_born_date',
            'student_gender',
            'student_address',
            'student_parent_phone',
            'student_name_of_mother',
            'student_name_of_father'
        ]);

        // Mengatur waktu dengan Carbon
        Carbon::setLocale('id');
        $waktu = Carbon::now()->translatedFormat('Y-m-d H:i:s');
        $data_update['student_input_date'] = $waktu;
        $data_update['student_last_update'] = $waktu;

        // Direktori upload
        // $uploadDirectory = $this->getUploadDirectory($domain, $location);
        $uploadDirectory = "../../$domain.ebimbel.co.id/uploads/student/";

        try {
            // Proses upload gambar
            if ($request->hasFile('student_img') && $request->file('student_img')->isValid()) {
                $file = $request->file('student_img');
                $extension = $file->getClientOriginalExtension();

                // Validasi ekstensi file
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
                if (!in_array(strtolower($extension), $allowedExtensions)) {
                    return response()->json(['message' => 'Invalid image type'], 400);
                }

                $fileName = time() . "_" . $file->getClientOriginalName();
                $destinationPath = public_path($uploadDirectory);

                // Pastikan direktori ada
                if (!is_dir($destinationPath)) {
                    mkdir($destinationPath, 0777, true);
                }

                // Pindahkan file
                $file->move($destinationPath, $fileName);

                // Simpan nama file di database
                $data_update['student_img'] = $fileName;
            } elseif ($request->input('student_img')) {
                $rawBase64 = $request->input('student_img');
                $extension = 'jpeg';
                $base64Image = "data:image/{$extension};base64," . $rawBase64;


                // Validasi format Base64
                if (!preg_match('/^data:image\/(jpeg|png|jpg|gif);base64,/', $base64Image)) {
                    return response()->json(['message' => 'Invalid Base64 format'], 400);
                }

                // Pisahkan data dan MIME type
                [$mimePart, $data] = explode(';base64,', $base64Image);
                $extension = str_replace('data:image/', '', $mimePart);

                if (!in_array($extension, ['jpeg', 'jpg', 'png', 'gif'])) {
                    return response()->json(['message' => 'Invalid image type'], 400);
                }

                $imageData = base64_decode($data);

                // Buat gambar dari string
                $image = imagecreatefromstring($imageData);

                $fileName = uniqid() . '.' . $extension;
                $destinationPath = public_path($uploadDirectory);

                // Pastikan direktori ada
                if (!is_dir($destinationPath)) {
                    mkdir($destinationPath, 0777, true);
                }
                $compressionQuality = 50;

                $this->compressImage($image, $destinationPath, $fileName, $compressionQuality);

                // Simpan file
                if (file_put_contents($destinationPath . '/' . $fileName, base64_decode($data)) === false) {
                    return response()->json(['message' => 'Failed to save image'], 500);
                }

                // Simpan nama file di database
                $data_update['student_img'] = $fileName;
            }

            // Update data siswa
            DB::table('student')
                ->where('student_id', $student->student_id) // Pastikan $student memiliki ID yang valid
                ->update($data_update);

            return response()->json([
                'is_correct' => true,
                'message' => 'Update successful',
                'data' => $data_update
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'is_correct' => false,
                'message' => 'Update failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
