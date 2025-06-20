<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\DataAbsen;
use Illuminate\Http\Request;
use App\Services\WhatsappService;
use App\Services\DatabaseSwitcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class DataAbsenController extends Controller
{
    // protected $databaseSwitcher;

    // // Inject DatabaseSwitcher di constructor
    // public function __construct(DatabaseSwitcher $databaseSwitcher)
    // {
    //     $this->databaseSwitcher = $databaseSwitcher;
    // }


    public function index(Request $request)
    {
        $validatedData = $request->validate([
            'id_pegawai' => 'nullable',
            'tahun' => 'nullable|integer|min:1900|max:' . date('Y'),
            'bulanan' => 'nullable|integer|min:1|max:12',
        ]);

        $id_pegawai = $request->input('id_pegawai');

        $tahun = $request->input('tahun');

        $bulanan = $request->input('bulanan');

        $query = DataAbsen::query();

        if ($id_pegawai) {
            $query->where('id_pegawai', $id_pegawai);
        }

        if ($bulanan) {
            $query->whereMonth('tanggal', $bulanan);
        }

        if ($tahun) {
            $query->whereYear('tanggal', $tahun);
        }

        $data = $query->paginate(10);

        if ($data->isEmpty()) {
            return response()->json([
                'is_correct' => false,
                'message' => 'data tidak ada'
            ]);
        }

        return response()->json([
            'is_correct' => true,
            'message' => 'success',
            'data' => $data
        ], 200);
    }

    /**
     * @OA\Post(
     *     path="/api/absen",
     *     summary="Absen ke hadiran",
     *     description="Mengirim data absensi dengan jenis absensi 'Datang' ",
     *     tags={"Absensi"},
     *     security={{"BearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         content={
     *             @OA\MediaType(
     *                 mediaType="multipart/form-data",
     *                 @OA\Schema(
     *                     required={"jenis_absen", "foto"},
     *                     @OA\Property(property="jenis_absen", type="string", enum={"Datang"}),
     *                     @OA\Property(property="foto", type="string", format="binary", description="Unggah foto bukti absensi"),
     *                     @OA\Property(property="catatan_absen", type="string", description="Catatan absensi"),
     *                     @OA\Property(property="area_absen", type="integer", description="id_area absen"),
     *                 )
     *             )
     *         }
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Data absensi berhasil disimpan",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="success")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validasi data absensi gagal",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Tidak dapat absen karena sudah SAKIT pada hari ini."),
     *             @OA\Property(property="error", type="object", additionalProperties={"type": "string"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - Token tidak valid atau kedaluwarsa",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized.")
     *         )
     *     )
     * )
     */


    public function store(Request $request)
    {
        // $this->databaseSwitcher->switchDatabaseFromToken(new DataAbsen());
        $user = auth()->user();

        $areaAbsensi = $user->areaAbsensi;
        $longitude = $areaAbsensi->longi;
        $latitude = $areaAbsensi->lati;
        $lokasi = $areaAbsensi->nama_area;

        //dd($user->waktu_indonesia);
        //dd($longi);
        $validator = Validator::make($request->all(), [
            'id_pegawai' => 'nullable',
            'jenis_absen' => 'required|in:Datang',
            'status_hadir' => 'nullable',
            'area_absen' => 'required',
            'bulan' => 'nullable',
            'tanggal' => 'nullable',
            'time' => 'nullable',
            'jam' => 'nullable',
            'longi' => 'nullable',
            'lati' => 'nullable',
            'lokasi' => 'nullable',
            'month' => 'nullable',
            'year' => 'nullable',
            'keterlambatan' => 'nullable',
            'foto' => 'required|image',
            'catatan_absen' => 'nullable',
            'remark' => 'nullable',
            'created_by' => 'nullable',
            'updated_by' => 'nullable',
            'updated_date' => 'nullable'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'is_correct' => false,
                'error' => $validator->errors()
            ]);
        }
        $waktu = $user->waktu_indonesia;
        $zonaWaktu = '';
        if ($waktu == 'WIB') {
            $zonaWaktu = 'Asia/Jakarta';
        }
        if ($waktu == 'WIT') {
            $zonaWaktu = 'Asia/Jayapura';
        }
        if ($waktu == 'WITA') {
            $zonaWaktu = 'Asia/Makassar';
        }
        $currentTime = Carbon::now($zonaWaktu);
        $startTime = Carbon::createFromTime(7, 0, 0, $zonaWaktu);
        $limitTime = Carbon::createFromTime(8, 0, 0, $zonaWaktu);

        //menentukan status hadir "tepat waktu" dan terlambat
        if ($currentTime->lessThanOrEqualTo($limitTime)) {
            $statusHadir = 'tepat waktu';
        } else {
            $totalMenitKeterlambatan = $currentTime->diffInMinutes($limitTime);
            $jamKeterlambatan = intdiv($totalMenitKeterlambatan, 60);
            $menitKeterlambatan = $totalMenitKeterlambatan % 60;
            $keteranganKeterlambatan = "Anda terlambat: $jamKeterlambatan jam lebih $menitKeterlambatan menit.";
            $statusHadir = 'terlambat';
        }

        //menentukan bulan
        $bulan = $currentTime->format('Y-m');
        $tahun = $currentTime->format('Y');
        // $tanggal =  $currentTime->format('Y-m-d');
        $tanggal = $request->tanggal ?? $currentTime->format('Y-m-d');
        // dd($tanggal);
        $absenPadaTanggalIni = DB::table('data_absensi')
            ->where('id_pegawai', $user->employee_id)
            ->where('tanggal', $tanggal)
            ->whereIn('jenis_absen', ['IJIN', 'SAKIT', 'Lain-lain'])
            ->first();
        // dd($absenPadaTanggalIni);
        if ($absenPadaTanggalIni) {
            $jenisAbsen = $absenPadaTanggalIni->jenis_absen;
            return response()->json([
                'is_correct' => false,
                'message' => "Tidak dapat absen karena sudah absen $jenisAbsen pada hari ini."
            ], 400);
        }

        $dataUntukDikirim = [
            'id_pegawai' => $user->employee_id,
            'jenis_absen' => $request->jenis_absen,
            'statushadir' => $statusHadir,
            'area_absen' => $request->area_absen,
            'bulan' => $bulan,
            'tanggal' => $tanggal,
            'time' => $currentTime->toTimeString(),
            'jam' => $currentTime->format('Y-m-d H:i:s'),
            'longi' => $longitude,
            'lati' => $latitude,
            'lokasi' => $lokasi,
            'month' => $request->month,
            'year' => $tahun,
            'keterlambatan' => $keteranganKeterlambatan,
            'foto' => $request->foto,
            'catatan_absen' => $request->catatan_absen,
            'remark' => $request->remark,
            'created_by' => $user->employee_name,
            'created_date' => $tanggal,
            'updated_by' => $user->employee_name
        ];

        if ($request->hasFile('foto')) {
            $file = $request->file('foto');

            // Generate nama file yang unik
            $filename = time() . '_' . $file->getClientOriginalName();



            // Simpan gambar ke folder storage/app/public/images
            $filePath = $file->storeAs('public/absen', $filename);
            //tempat base url nya
            $baseUrl = env('L5_SWAGGER_CONST_HOST');

            $dataUntukDikirim['foto'] = $baseUrl . '/storage/absen/' . $filename;
        }
        //$data = DataAbsen::create($dataUntukDikirim);
        //dd($filePath);

        $data = DB::table('data_absensi')->insertGetId($dataUntukDikirim);
        $whatsappService = new WhatsappService();
        $noWa = $user->employee_phone;
        //dd($noWa);

        $pesan = "Report Presensi " . Carbon::now()->format('d F Y') . "\n";
        $pesan .= "==================================\n";
        $pesan .= "Nama : " . $user->employee_name . "\n";
        $pesan .= "Prepare : 07:30:00-08:00:00\n";
        $pesan .= "Presensi Masuk : " . $dataUntukDikirim['time'] . " ($statusHadir)\n";

        // Kondisi Pesan Berdasarkan Kehadiran
        if ($statusHadir === 'Tepat Waktu') {
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
            'message' => 'success'
        ], 200);
    }

    /**
     * @OA\Post(
     *     path="/api/pulang",
     *     summary="Absen Pulang",
     *     description="Mengirim data absensi dengan jenis absensi 'Pulang' ",
     *     tags={"Absensi"},
     *     security={{"BearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         content={
     *             @OA\MediaType(
     *                 mediaType="multipart/form-data",
     *                 @OA\Schema(
     *                     required={"jenis_absen", "foto"},
     *                     @OA\Property(property="jenis_absen", type="string", example="PULANG", enum={"Pulang"}),
     *                     @OA\Property(property="foto", type="string", format="binary", description="Unggah foto bukti absensi"),
     *                     @OA\Property(property="catatan_absen", type="string", description="Catatan absensi"),
     *                     @OA\Property(property="area_absen", type="integer", description="area absensi"),
     *                 )
     *             )
     *         }
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Data absensi berhasil disimpan",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="success")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validasi data absensi gagal",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=false),
     *             @OA\Property(property="error", type="object", additionalProperties={"type": "string"})
     *         )
     *     )
     * )
     */
    public function storePulang(Request $request)
    {
        //$this->databaseSwitcher->switchDatabaseFromToken(new DataAbsen());
        $user = auth()->user();

        $areaAbsensi = $user->areaAbsensi;
        $longitude = $areaAbsensi->longi;
        $latitude = $areaAbsensi->lati;
        $lokasi = $areaAbsensi->nama_area;

        //dd($user->areaAbsensi);
        //dd($longi);
        $validator = Validator::make($request->all(), [
            'id_pegawai' => 'nullable',
            'jenis_absen' => 'required|in:PULANG',
            'status_hadir' => 'nullable',
            'statusprepare' => 'nullable',
            'area_absen' => 'required',
            'bulan' => 'nullable',
            'tanggal' => 'nullable',
            'time' => 'nullable',
            'jam' => 'nullable',
            'longi' => 'nullable',
            'lati' => 'nullable',
            'lokasi' => 'nullable',
            'month' => 'nullable',
            'year' => 'nullable',
            'keterlambatan' => 'nullable',
            'foto' => 'required|image',
            'catatan_absen' => 'nullable',
            'remark' => 'nullable',
            'created_by' => 'nullable',
            'updated_by' => 'nullable',
            'updated_date' => 'nullable'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'is_correct' => false,
                'error' => $validator->errors()
            ]);
        }
        $waktu = $user->waktu_indonesia;
        $zonaWaktu = '';
        if ($waktu == 'WIB') {
            $zonaWaktu = 'Asia/Jakarta';
        }
        if ($waktu == 'WIT') {
            $zonaWaktu = 'Asia/Jayapura';
        }
        if ($waktu == 'WITA') {
            $zonaWaktu = 'Asia/Makassar';
        }
        $currentTime = Carbon::now($zonaWaktu);
        $startTime = Carbon::createFromTime(7, 0, 0, $zonaWaktu);
        $limitTime = Carbon::createFromTime(16, 0, 0, $zonaWaktu);

        $keteranganKeterlambatan = '';
        $statusHadir = '';

        // Menentukan status pulang tepat waktu atau lebih cepat
        if ($currentTime->greaterThanOrEqualTo($limitTime)) {
            $statusHadir = 'tepat waktu';
            $pesan = "Report Presensi {$currentTime->format('d F Y')}
==================================
Nama : {$user->employee_name}
Closing Report : 16:00:00-16:30:00
Presensi Pulang : {$currentTime->toTimeString()} (Pulang)
Terima kasih sudah pulang sesuai aturan. Semoga harimu bermanfaat.";
        } else {
            $totalMenitLebihCepat = $limitTime->diffInMinutes($currentTime);
            $jamLebihCepat = intdiv($totalMenitLebihCepat, 60);
            $menitLebihCepat = $totalMenitLebihCepat % 60;

            $keteranganKeterlambatan = "Anda pulang lebih cepat: $jamLebihCepat jam lebih $menitLebihCepat menit.";
            $statusHadir = 'lebih cepat';
            $pesan = "Report Presensi {$currentTime->format('d F Y')}
==================================
Nama : {$user->employee_name}
Closing Report : 16:00:00-16:30:00
Presensi Pulang : {$currentTime->toTimeString()} (Pulang Awal)
Anda pulang $jamLebihCepat jam $menitLebihCepat menit lebih awal. Keterangan : {$request->catatan_absen}";
        }
        // Menentukan status pulang tepat waktu atau lebih cepat
        if ($currentTime->greaterThanOrEqualTo($limitTime)) {
            $statusHadir = 'tepat waktu';
        } else {
            $totalMenitKeterlambatan = $limitTime->diffInMinutes($currentTime);
            $jamKeterlambatan = intdiv($totalMenitKeterlambatan, 60);
            $menitKeterlambatan = $totalMenitKeterlambatan % 60;
            $keteranganKeterlambatan = "Anda pulang lebih cepat: $jamKeterlambatan jam lebih $menitKeterlambatan menit.";
            $statusHadir = 'lebih cepat';
        }

        //menentukan bulan
        $bulan = $currentTime->format('Y-m');
        $tahun = $currentTime->format('Y');
        $tanggal =  $currentTime->format('Y-m-d');

        $dataUntukDikirim = [
            'id_pegawai' => $user->employee_id,
            'jenis_absen' => $request->jenis_absen,
            'statushadir' => $statusHadir,
            'area_absen' => $request->area_absen,
            'bulan' => $bulan,
            'statusprepare' => '',
            'tanggal' => $tanggal,
            'time' => $currentTime->toTimeString(),
            'jam' => $currentTime->format('Y-m-d H:i:s'),
            'longi' => $longitude,
            'lati' => $latitude,
            'lokasi' => $lokasi,
            'month' => $request->month,
            'year' => $request->year,
            'keterlambatan' => $keteranganKeterlambatan,
            'foto' => $request->foto,
            'catatan_absen' => $request->catatan_absen,
            'remark' => $request->remark,
            'created_by' => $user->employee_name,
            'created_date' => $tanggal,
            'updated_by' => $user->employee_name
        ];

        if ($request->hasFile('foto')) {
            $file = $request->file('foto');

            // Generate nama file yang unik
            $filename = time() . '_' . $file->getClientOriginalName();

            // Simpan gambar ke folder storage/app/public/images
            $filePath = $file->storeAs('public/absen', $filename);

            $dataUntukDikirim['foto'] = $filePath;
        }
        $data = DataAbsen::create($dataUntukDikirim);
        //$data = DB::connection('sekolah')->table('data_absensi')->insertGetId($dataUntukDikirim);
        $whatsappService = new WhatsappService();
        $noWa = $user->employee_phone;
        $whatsappService->kirimPesan($noWa, $pesan);
        return response()->json([
            'is_correct' => true,
            'message' => 'suceess'
        ], 200);
    }


    /**
     * @OA\Post(
     *     path="/api/izin",
     *     summary="Absen IZIN",
     *     description="Mengirim data absensi dengan jenis absensi 'IZIN' ",
     *     tags={"Absensi"},
     *     security={{"BearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         content={
     *             @OA\MediaType(
     *                 mediaType="multipart/form-data",
     *                 @OA\Schema(
     *                     required={"jenis_absen", "foto","catatan_absen","tanggal"},
     *                     @OA\Property(property="jenis_absen", type="string", example="IJIN", enum={"IJIN","SAKIT","Lain-lain"}),
     *                     @OA\Property(property="foto", type="string", format="binary", description="Unggah foto bukti absensi"),
     *                     @OA\Property(property="catatan_absen", type="string", description="Catatan absensi"),
     *                     @OA\Property(property="tanggal", type="string", format="date", description="Tanggal absensi"),
     *                 )
     *             )
     *         }
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Data absensi berhasil disimpan",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="success")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validasi data absensi gagal",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_correct", type="boolean", example=false),
     *             @OA\Property(property="error", type="object", additionalProperties={"type": "string"})
     *         )
     *     )
     * )
     */

    public function storeIzin(Request $request)
    {
        // $this->databaseSwitcher->switchDatabaseFromToken(new DataAbsen());
        $user = auth()->user();

        $areaAbsensi = $user->areaAbsensi;
        $longitude = $areaAbsensi->longi;
        $latitude = $areaAbsensi->lati;
        $lokasi = $areaAbsensi->nama_area;

        $validator = Validator::make($request->all(), [
            'id_pegawai' => 'nullable',
            'jenis_absen' => 'required|in:IJIN,SAKIT,Lain-lain',
            'tanggal_awal' => 'required|date',
            'tanggal_akhir' => 'required|date|after_or_equal:tanggal_awal',
            'foto' => 'required|mimes:jpeg,jpg,png,pdf,doc,docx|max:2048',
            'catatan_absen' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'is_correct' => false,
                'error' => $validator->errors()
            ]);
        }
        $waktu = $user->waktu_indonesia;
        $zonaWaktu = '';
        if ($waktu == 'WIB') {
            $zonaWaktu = 'Asia/Jakarta';
        }
        if ($waktu == 'WIT') {
            $zonaWaktu = 'Asia/Jayapura';
        }
        if ($waktu == 'WITA') {
            $zonaWaktu = 'Asia/Makassar';
        }

        $currentTime = Carbon::now($zonaWaktu);
        $bulan = $currentTime->format('Y-m');
        $tahun = $currentTime->format('Y');

        $tanggalAwal = Carbon::parse($request->tanggal_awal);
        $tanggalAkhir = Carbon::parse($request->tanggal_akhir);

        // Iterasi rentang tanggal
        $tanggalIterasi = $tanggalAwal->copy();
        $dataDimasukkan = [];
        $absenConflict = [];

        if ($request->hasFile('foto')) {
            $file = $request->file('foto');
            $filename = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('public/absen', $filename);
        }

        while ($tanggalIterasi->lte($tanggalAkhir)) {
            $tanggal = $tanggalIterasi->format('Y-m-d');

            // Cek apakah sudah ada absen "Datang" untuk tanggal tersebut
            $absenPadaTanggalIni = DB::table('data_absensi')
                ->where('id_pegawai', $user->employee_id)
                ->where('tanggal', $tanggal)
                ->where('jenis_absen', 'Datang')
                ->exists();

            if ($absenPadaTanggalIni) {
                $absenConflict[] = $tanggal;
            } else {
                // Data untuk disimpan
                $dataUntukDikirim = [
                    'id_pegawai' => $user->employee_id,
                    'jenis_absen' => $request->jenis_absen,
                    'area_absen' => $user->area_absen,
                    'bulan' => $bulan,
                    'tanggal' => $tanggal,
                    'time' => $currentTime->toTimeString(),
                    'jam' => $currentTime->format('Y-m-d H:i:s'),
                    'longi' => $longitude,
                    'lati' => $latitude,
                    'lokasi' => $lokasi,
                    'year' => $tahun,
                    'foto' => isset($filePath) ? $filePath : null,
                    'catatan_absen' => $request->catatan_absen,
                    'created_by' => $user->employee_name,
                    'created_date' => $tanggal,
                    'updated_by' => $user->employee_name,
                ];

                // Simpan data
                DataAbsen::create($dataUntukDikirim);
                $dataDimasukkan[] = $tanggal;
            }

            $tanggalIterasi->addDay();
        }

        if (!empty($absenConflict)) {
            return response()->json([
                'is_correct' => false,
                'message' => 'Tidak dapat izin pada tanggal: ' . implode(', ', $absenConflict) . ' karena sudah ada absen Datang.',
            ], 400);
        }

        return response()->json([
            'is_correct' => true,
            //'message' => 'Izin berhasil diajukan untuk tanggal: ' . implode(', ', $dataDimasukkan),
            'message' => 'success'
        ], 200);
    }
}
