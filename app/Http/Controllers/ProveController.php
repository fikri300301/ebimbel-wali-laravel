<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Prove;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use function Laravel\Prompts\error;

class ProveController extends Controller
{
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
        // dd('coba');
        $token = JWTAuth::parseToken();

        // Get the token payload
        $claims = $token->getPayload();

        $location = $claims->get('location');
        $domain = $claims->get('folder');
        $user = auth()->user();
        $data = Prove::where('prove_student_id', $user->student_id)->orderBy('prove_date', 'desc')->get()->map(function ($item) {
            return [
                'prove_id' => $item->prove_id,
                'tanggal' => $item->prove_date,
                'prove_note' => $item->prove_note,
                'prove_status' => $item->prove_status,

            ];
        });



        // if ($data) {
        return response()->json([
            'is_correct' => true,
            'is_message' => 'success',
            'prove' => $data
        ], 200);
        // } else {
        //     return response()->json([
        //         'is_correct' => false,
        //         'is_message' => 'data not found'
        //     ]);
        // }
    }
    function getUploadDirectory($domain, $location)
    {
        $defaultDirectory = "../../../$domain/uploads/prove/";


        if ($domain == 'demo') {

            // return "../public_html/$domain/uploads/prove/";  // Untuk domain demo

            return "../../public_html/$domain/uploads/prove/";
        }
        if ($domain == "alrisalah") {

            return "../../../alrisalah2/uploads/prove/"; // Untuk domain alrisalah
        }

        switch ($location) {
            case '1':
                return "../../public_html/backup/$domain/uploads/prove/";
            case '2':
                return "../../public_html/$domain/uploads/prove/";
            case '3':
                return "../../$domain.adminsekolah.net/uploads/prove/";
            default:
                return $defaultDirectory;
        }
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        $token = JWTAuth::parseToken();

        // Get the token payload
        $claims = $token->getPayload();

        $location = $claims->get('location');
        $domain = $claims->get('folder'); // Ambil dari request atau sumber yang relevan
        // dd($domain);
        // Validasi data request
        $validator = Validator::make($request->all(), [
            'prove_date' => 'nullable|date',
            //'prove_image' => 'required', // Ganti 'required|string' menjadi hanya 'required'
            'prove_image' => 'required|' . ($request->hasFile('prove_image') ? 'image|mimes:jpeg,png,jpg,gif' : 'string'),
            'prove_note' => 'required|string',
            'prove_status' => 'nullable|integer',
            'prove_student_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            // Periksa apakah input adalah file atau Base64
            $filePath = null;
            $imageUrl = null;

            // Tentukan direktori upload sesuai dengan fungsi getUploadDirectory
            //   $uploadDirectory = $this->getUploadDirectory($domain, $location);
            $uploadDirectory = "../../$domain.ebimbel.co.id/uploads/prove/";

            if ($request->hasFile('prove_image') && $request->file('prove_image')->isValid()) {
                $file = $request->file('prove_image');
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
                $base64Image = $request->input('prove_image');

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

                $imageData = base64_decode($data);

                // Buat gambar dari string
                $image = imagecreatefromstring($imageData);

                if ($image === false) {
                    return response()->json(['message' => 'Failed to create image from Base64 data'], 500);
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

                $compressionQuality = 100; // Kualitas kompresi (0-100)


                $this->compressImage($image, $publicPath, $fileName, $compressionQuality);

                // Simpan gambar langsung di folder yang sesuai
                if (file_put_contents($publicPath . '/' . $filePath, $decodedImage) === false) {
                    return response()->json(['message' => 'Failed to save image'], 500);
                }

                // Dapatkan URL untuk gambar Base64
                $imageUrl = asset($uploadDirectory . '/' . $filePath);
            }

            // Simpan data ke database, termasuk hanya nama file
            $prove = Prove::create([
                'prove_date' => Carbon::now('Asia/Jakarta'),
                'prove_note' => $request->input('prove_note'),
                'prove_status' => $request->input('prove_status', 2),
                'prove_student_id' => $user->student_id,
                'prove_img' => $filePath, // Simpan hanya nama file
            ]);

            // Set the final URL directly to the desired base domain
            $finalImageUrl = "https://" . $domain . ".ebimbel.co.id/uploads/prove/" . $fileName;
            //  dd($fileName);
            return response()->json([
                'is_correct' => true,
                'message' => 'Success',
                'prove_img' => $finalImageUrl // Return the correct URL with the desired base domain
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'is_correct' => false,
                'message' => 'Failed to upload image or save data',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function store1(Request $request)
    {
        $user = auth()->user();
        $token = JWTAuth::parseToken();

        // Get the token payload
        $claims = $token->getPayload();

        $location = $claims->get('location');
        $folder = $claims->get('folder');

        // dd($folder);
        // Validasi data request
        $validator = Validator::make($request->all(), [
            'prove_date' => 'nullable|date',
            'prove_image' => 'required', // Ganti 'required|string' menjadi hanya 'required'
            'prove_note' => 'required|string',
            'prove_status' => 'nullable|integer',
            'prove_student_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            // Periksa apakah input adalah file atau Base64
            $filePath = null;
            $imageUrl = null;

            // Jika input adalah file biasa (bukan Base64)
            if ($request->hasFile('prove_image') && $request->file('prove_image')->isValid()) {
                $file = $request->file('prove_image');

                // Validasi ekstensi file
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
                $extension = $file->getClientOriginalExtension();

                if (!in_array(strtolower($extension), $allowedExtensions)) {
                    return response()->json(['message' => 'Invalid image type'], 400);
                }

                $imageName = uniqid() . '.' . $extension;

                // Tentukan folder tujuan
                $destinationPath = public_path('konfirmasi');


                // Pindahkan file ke folder 'public/konfirmasi'
                $file->move($destinationPath, $imageName);
                // $file->move($destinationPath, $imageName);

                // Cek apakah file berhasil dipindahkan
                if (!file_exists($destinationPath . '/' . $imageName)) {
                    return response()->json(['message' => 'Failed to move the image'], 500);
                }

                // Dapatkan URL untuk file yang di-upload
                $filePath = ('konfirmasi/' . $imageName);
                // $imageUrl = asset($filePath);

            } else {
                // Jika input berupa Base64
                $base64Image = $request->input('prove_image');

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

                // Tentukan path untuk menyimpan gambar di folder 'public/konfirmasi'
                $imageName = uniqid() . '.' . $mimeType;
                $publicPath = public_path('konfirmasi');

                // Tentukan path lengkap untuk gambar
                $filePath = 'konfirmasi/' . $imageName;

                // Simpan gambar langsung di folder 'public/konfirmasi'
                if (file_put_contents(public_path($filePath), $decodedImage) === false) {
                    return response()->json(['message' => 'Failed to save image'], 500);
                }

                // Dapatkan URL untuk gambar Base64
                $imageUrl = asset($filePath);
            }

            // dd($filePath);
            // Simpan data ke database, termasuk path gambar
            $prove = Prove::create([
                'prove_date' => Carbon::now('Asia/Jakarta'),
                'prove_note' => $request->input('prove_note'),
                'prove_status' => $request->input('prove_status', 2), // Default status 2 jika tidak ada
                'prove_student_id' => $user->student_id,
                'prove_img' => $filePath, // Isi kolom prove_img dengan path relatif gambar
            ]);

            return response()->json([
                'is_correct' => true,
                'message' => 'Success',
                'prove_img' => $imageUrl
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'is_correct' => false,
                'message' => 'Failed to upload image or save data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function detail($id)
    {
        $user = auth()->user();
        $token = JWTAuth::parseToken();

        // Get the token payload
        $claims = $token->getPayload();

        $location = $claims->get('location');
        $domain = $claims->get('folder');
        $data = Prove::where('prove_student_id', $user->student_id)->where('prove_id', $id)->first();
        //dd($data);


        $photoUrl = asset(($data->prove_img) ?? null);
        $fileName = $data->prove_img;
        if ($data) {
            return response()->json([
                'is_correct' => true,
                'message' => 'succes',
                'tanggal' => $data->prove_date,
                'note' => $data->prove_note,
                'prove_img' => "https://" . $domain . ".ebimbel.co.id/uploads/prove/" . $fileName,
                'foto' =>  "https://" . $domain . ".ebimbel.co.id/uploads/prove/" . $fileName,
                'prove_status' => $data->prove_status
            ], 200);
        } else {
            return response()->json([
                'error' => 'data not found'
            ], 400);
        }
    }



    public function update(Request $request, $id)
    {
        $user = auth()->user();

        // Validasi data request
        $validator = Validator::make($request->all(), [
            'prove_status' => 'required|integer|in:1,2,3'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'is_correct' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $prove = Prove::findOrFail($id);

            // Tentukan pesan berdasarkan status
            $message = '';
            if ($request->prove_status == 1) {
                $message = 'Terima kasih, pembayaran telah diverifikasi.';
            } elseif ($request->prove_status == 3) {
                $message = 'Mohon maaf, bukti transfer kami tolak karena tidak sesuai';
            }

            // Update data
            $prove->update([
                'prove_status' => $request->prove_status,
                'prove_note' => $message
            ]);

            // Kembalikan respons berhasil
            return response()->json([
                'is_correct' => true,
                'message' => 'success',
                //  'data' => $prove
            ], 200);
        } catch (ModelNotFoundException $e) {
            // Jika data tidak ditemukan
            return response()->json([
                'is_correct' => false,
                'message' => 'Data not found',
                'error' => $e->getMessage()
            ], 404);
        } catch (\Exception $e) {
            // Jika ada kesalahan umum
            return response()->json([
                'is_correct' => false,
                'message' => 'Failed to update data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function delete($id)
    {
        $user = auth()->user();
        $student = $user->student_id;
        $data = Prove::where('prove_id', $id)->where('prove_status', 2)->where('prove_student_id', $student)->first();
        if ($data) {
            $data->delete();
            return response()->json([
                'is_correct' => true,
                'message' => 'success'
            ], 200);
        } else {
            return response()->json([
                'is_correct' => false,
                'message' => 'Unauthorized or data not found'
            ], 400);
        }
    }
}
