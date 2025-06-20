<?php

namespace App\Http\Controllers;

use App\Models\data_ipaymu_channel;
use Carbon\Carbon;
use App\Models\Donasi;
use App\Models\Program;
use App\Models\Setting;
use App\Models\FlipDonasi;
use App\Models\FlipChannel;
use App\Models\IpaymuDonasi;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use App\Services\WhatsappServicePembayaran;

class DonasiController extends Controller
{

    public function index()
    {

        $token = JWTAuth::parseToken();

        // Get the token payload
        $claims = $token->getPayload();

        $folder = $claims->get('folder');
        $isFlip = Setting::where('setting_name', 'api_secret_key')->first();
        //dd($isFlip);
        $data = Program::all()->map(function ($item) use ($folder, $isFlip) {
            return [
                'program_id' => $item->program_id,
                'program_name' => $item->program_name,
                //'program_description' => $item->program_description,
                'program_gambar' => "http://$folder.adminsekolah.net/uploads/program/" . $item->program_gambar,
                'program_target' => $item->program_target,
                'terkumpul' => $item->program_earn,
                'program_end_date' => $item->program_end,
            ];
        });

        return response()->json([
            'is_correct' => true,
            'message' => 'success',
            'is_flip' => $isFlip ? true : false,
            'donasi' => $data
        ], 200);
    }

    public function daftarDonasi(Request $request)
    {
        // Menentukan jumlah item per halaman
        $perPage = $request->input('per_page');
        $programId = $request->input('program_id');

        // Mengambil data dengan pagination
        $data = Donasi::where('donasi_status', 1)->where('program_program_id', $programId)
            ->paginate($perPage);

        //  dd($data);
        // Transformasi data menggunakan map
        $transformedData = $data->getCollection()->map(function ($item) {
            return [
                'program_id' => $item->program_program_id,
                'nominal' => $item->donasi_nominal,
                'donasi_note' => $item->donasi_note ?? null,
                'name' => $item->donasi_name,
                'datetime' => $item->donasi_datetime,
            ];
        });

        // Mengganti collection yang ada di pagination dengan data yang sudah di-transform
        $data->setCollection($transformedData);

        return response()->json([
            'is_correct' => true,
            'message' => 'success',
            'donatur' => $data->items(), // Data yang dipaginasi
            'pagination' => [
                'total' => $data->total(), // Total jumlah data
                'per_page' => $data->perPage(), // Jumlah item per halaman
                'current_page' => $data->currentPage(), // Halaman saat ini
                'last_page' => $data->lastPage(), // Halaman terakhir
            ]
        ], 200);
    }

    public function riwayatTransaksi(Request $request, $start_date = null, $end_date = null)
    {
        $user = auth()->user();
        if (!$start_date) {
            $start_date = $request->input('start_date');
        }
        if (!$end_date) {
            $end_date = $request->input('end_date');
        }

        $query = FlipDonasi::where('student_id', $user->student_id)
            ->whereIn('status', ['FAILED', 'SUCCESSFUL', 'PENDING']);

        if ($start_date) {
            $query->where('tanggal', '>=', $start_date); // Filter jika start_date diisi
        }
        if ($end_date) {
            $endDate = Carbon::parse($end_date)->endOfDay(); // Pastikan end_date mencakup waktu hingga akhir hari
            $query->where('tanggal', '<=', $endDate); // Filter jika end_date diisi
        }

        $query->orderBy('id_transaksi', 'desc');
        $data = $query->get()->map(function ($item) {
            return [
                'id' => $item->id_transaksi,
                'status' => $item->status,
                'nominal' => $item->nominal,
                'tanggal' => $item->tanggal,
            ];
        });

        return response()->json([
            'is_correct' => true,
            'message' => 'success',
            'riwayat' => $data
        ]);
    }

    public function detailRiwayat($id)
    {
        $user = auth()->user();
        $data = FlipDonasi::where('student_id', $user->student_id)->where('id_transaksi', $id)->first();
        $fee = $data->va_fee == "null" ? '0' : $data->va_fee;

        $bank = strtoupper($data->va_bank);

        $fotoBank = FlipChannel::where('kode', $bank)->first();
        // dd($fee);
        if ($data) {
            return response()->json([
                'is_correct' => true,
                'message' => 'success',
                'status' => $data->status,
                'total_pembayaran' => $data->nominal,
                'mitra' => $data->va_bank,
                'noVa' => $data->va_no,
                'tenggat' => $data->Expired,
                'biaya' => $data->nominal - $fee,
                'fee' => (int)$data->va_fee,
                'foto' => 'https://mobile.epesantren.co.id/walsan/assets/logo_bank/' . ($fotoBank->logo ?? "")
            ]);
        }
    }

    public function show($id)
    {
        $token = JWTAuth::parseToken();

        // Get the token payload
        $claims = $token->getPayload();

        $folder = $claims->get('folder');

        $data = Program::where('program_id', $id)->first();
        $user = auth()->user();
        $dataDonatur = Donasi::where('donasi_status', 1)->where('program_program_id', $id)->get();
        $count = $dataDonatur->count();

        //  dd($count);
        if ($data != null) {
            return response()->json([
                'is_correct' => true,
                'message' => 'success',
                'jumlah_donatur' => $count,
                'program_id' => $data->program_id,
                'program_name' => $data->program_name,
                'program_description' => $data->program_description,
                'program_gambar' => "http://$folder.adminsekolah.net/uploads/program/" . $data->program_gambar,
                'program_target' => $data->program_target,
                'terkumpul' => $data->program_earn,
                'program_end_date' => $data->program_end,
                'nama' => $user->student_full_name,
                'alamat' => $user->student_address,
                'no_hp' => $user->student_parent_phone,
            ], 200);
        } else {
            return response()->json([
                'is_correct' => true,
                'message' => 'success',
                'data' => []
            ], 200);
        }
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        $token = JWTAuth::parseToken();

        // Get the token payload
        $claims = $token->getPayload();

        $kodePondok = $claims->get('kode_sekolah');
        $validator = Validator::make($request->all(), [
            //donasi
            'id' => 'required',
            'donasi_ref_id' => 'nullable',
            'donasi_nominal' => 'required',
            'donasi_status' => 'nullable',
            'donasi_name' => 'nullable',
            'donasi_alamat' => 'nullable',
            'donasi_hp' => 'nullable',
            'donasi_datetime' => 'nullable',

            //flip_donasi
            'kode_pondok' => 'nullable',
            'student_id' => 'nullable',
            'noref' => 'nullable',
            'tanggal' => 'nullable',
            'status' => 'nullable',
            'dibayar' => 'nullable',
            'va_no' => 'nullable',
            'va_nama' => 'nullable',
            'va_channel' => 'nullable',
            'va_bank' => 'nullable',
            'transactionId' => 'nullable',
            'va_fee' => 'nullable',
            'create_at' => 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'is_correct' => false,
                'message' => 'error',
                'data' => $validator->errors()
            ], 400);
        }
        $nominal = $request->donasi_nominal;
        // $donasiName = $user->student_name_of_mother;
        // $alamat = $user->student_address;
        // $hp = $user->student_parent_phone;
        $ref = idate('U');
        $lokasi = $user->waktu_indoesia;
        //dd($request);
        $donasi = Donasi::create([
            'program_program_id' => $request->id,
            'donasi_nominal' => $request->donasi_nominal,
            'donasi_status' => 0,
            'donasi_ref_id' => $ref,
            'donasi_name' => $request->donasi_name,
            'donasi_alamat' => $request->donasi_alamat,
            'donasi_hp' => $request->donasi_hp,
            'donasi_datetime' => now($lokasi),
        ]);

        $flipDonasi = FlipDonasi::create([
            'kode_pondok' => $kodePondok,
            'student_id' => $user->student_id,
            'noref' => $ref,
            'tanggal' =>  now($lokasi),
            'nominal' => $request->donasi_nominal,
            'status' => 'PENDING',
            'dibayar' => 0,
            'va_no' => 'null',
            'va_nama' => 'null',
            'va_channel' => 'null',
            'va_bank' => 'null',
            'transactionId' => 'null',
            'va_fee' => 'null',
            'create_at' => now($lokasi),
        ]);

        return response()->json([
            'is_correct' => true,
            'message' => 'success',
            'nominal' => $nominal,
            'noref' => $ref
        ], 200);
    }

    public function processPayment(Request $request)
    {
        $setting = Setting::where('setting_name', 'api_secret_key')->first();
        //  dd($setting);
        if ($setting) {

            try {
                $user = auth()->user();
                $token = JWTAuth::parseToken();

                // Get the token payload
                $claims = $token->getPayload();

                $kodePondok = $claims->get('kode_sekolah');
                $claims = $token->getPayload();
                $kode_sekolah = $claims->get('kode_sekolah');
                $nama_sekolah = $claims->get('schoolName');
                $PaymentGateway = $claims->get('payment');
                // dd($PaymentGateway);
                if ($PaymentGateway != 'AKTIF') {
                    return response()->json([
                        'message' => 'Anda tidak terdaftar dalam payment Gateway'
                    ], 409);
                }
                $validator = Validator::make($request->all(), [
                    //donasi
                    'id' => 'required',
                    'donasi_ref_id' => 'nullable',
                    'donasi_nominal' => 'required',
                    'donasi_status' => 'nullable',
                    'donasi_name' => 'nullable',
                    'donasi_alamat' => 'nullable',
                    'donasi_note' => 'nullable',
                    'donasi_hp' => 'nullable',
                    'donasi_datetime' => 'nullable',

                    //flip_donasi
                    'kode_pondok' => 'nullable',
                    'student_id' => 'nullable',
                    'noref' => 'nullable',
                    'tanggal' => 'nullable',
                    'status' => 'nullable',
                    'dibayar' => 'nullable',
                    'va_no' => 'nullable',
                    'va_nama' => 'nullable',
                    'va_channel' => 'nullable',
                    'va_bank' => 'nullable',
                    'transactionId' => 'nullable',
                    'va_fee' => 'nullable',
                    'create_at' => 'nullable',
                ]);

                if ($validator->fails()) {
                    return response()->json([
                        'is_correct' => false,
                        'message' => 'error',
                        'data' => $validator->errors()
                    ], 400);
                }
                $nominal = $request->donasi_nominal;
                $donasiName = $user->student_name_of_mother;
                $alamat = $user->student_address;
                $hp = $user->student_parent_phone;
                $ref = idate('U');
                $lokasi = $user->waktu_indonesia;
                //    dd($user);
                $waktu = $user->waktu_indonesia;
                $waktuAsli = Carbon::now('Asia/jakarta')->format('Y-m-d H:i:s');
                //    dd($waktuAsli);
                if ($waktu == 'WIT') {
                    $waktuAsli = Carbon::now('Asia/Makassar')->format('Y-m-d H:i:s');
                }
                if ($waktu == 'WITA') {
                    $waktuAsli = Carbon::now('Asia/Jayapura')->format('Y-m-d H:i:s');
                }
                $donasi = Donasi::create([
                    'program_program_id' => $request->id,
                    'donasi_nominal' => $request->donasi_nominal,
                    'donasi_status' => 0,
                    'donasi_ref_id' => $ref,
                    'donasi_name' => $request->donasi_name,
                    'donasi_note' => $request->donasi_note,
                    'donasi_alamat' => $request->donasi_alamat,
                    'donasi_hp' => $request->donasi_hp,
                    'donasi_datetime' => $waktuAsli,
                ]);

                $flipDonasi = FlipDonasi::create([
                    'kode_pondok' => $kodePondok,
                    'student_id' => $user->student_id,
                    'noref' => $ref,
                    'tanggal' =>  now($lokasi),
                    'nominal' => $request->donasi_nominal,
                    'status' => 'PENDING',
                    'dibayar' => 0,
                    'va_no' => 'null',
                    'va_nama' => 'null',
                    'va_channel' => 'null',
                    'va_bank' => 'null',
                    'transactionId' => 'null',
                    'va_fee' => 'null',
                    'create_at' => now($lokasi),
                ]);


                if ($validator->fails()) {
                    return response()->json([
                        'is_correct' => false,
                        'message' => 'error',
                        'data' => $validator->errors()
                    ], 400);
                }

                $user = auth()->user();
                $query = FlipDonasi::where('noref', $ref)->first();

                // if ($query) {
                $nis = $user->student_nis;
                $request->validate([
                    'payment_channel' => 'required',
                ]);
                // }

                $nominal = $request->donasi_nominal;
                $payment_channel = $request->payment_channel;
                $caraBayar = FlipChannel::where('payment_channel', $payment_channel)->first();
                $fee = $caraBayar->fee;
                $totalBayar = $nominal + $fee;

                $paymentChannelParts = explode('|', $payment_channel);
                $paymentMethod = $paymentChannelParts[0];

                if (count($paymentChannelParts) !== 2) {
                    return response()->json([
                        'is_correct' => 'error',
                        'message' => 'Invalid payment channel',
                    ], 400);
                }

                $nameEmail = explode(" ", $user->student_full_name);
                $firstName = $nameEmail[0];
                // $lastName = $nameEmail[1];
                $lastName = $nameEmail[1] ?? '';
                $email = $this->RemoveSpecialChar($firstName) . $this->RemoveSpecialChar($lastName) . '@epesantren.co.id';

                $senderBank = strtolower($paymentChannelParts[1]);

                $kodeBayar = 3;
                $Transaksi = 'donasi';
                $payload = [
                    'title' => 'Pembayaran atas nama ' .  $user->student_full_name . '|' . $nis . ' | ' . $kode_sekolah . ' | ' . $request->id . '|' . $kodeBayar,
                    'amount' => $nominal + $fee,
                    'total_bayar' => $totalBayar,
                    'fee' => $fee,
                    'type' => 'SINGLE',
                    'expired_date' => now('WIB')->addHours(6)->format('Y-m-d H:i'),
                    //'redirect_url' => "https://mobile.epesantren.co.id/walsan/callback_semua.php",
                    'redirect_url' => env('REDIRECT_URL_FLIP'),
                    'is_address_required' => 0,
                    'is_phone_number_required' => 0,
                    'step' => 3,
                    // 'sender_name' => $user->student_full_name,
                    'sender_name' => $this->RemoveSpecialChar($user->student_full_name),
                    'sender_email' => $email,
                    'sender_bank' => $senderBank,
                    'sender_bank_type' => $paymentMethod,
                    'user_id' => "1"
                ];

                $flipConfig = $this->getFlipConfig();
                //  dd($flipConfig);
                $response = $this->createPaymentToFlip($payload, $flipConfig, $ref);
                //   dd($response);
                if ($response['is_correct'] === 'error') {
                    throw new \Exception("Gagal membuat Virtual Account. Silakan coba lagi.");
                }
                // dd($ref);
                $update = FlipDonasi::where('noref', $ref)->update([
                    'va_no' => $response['va_number'],
                    'va_channel' => $response['label'],
                    'va_bank' => $response['bank'],
                    // 'transactionId' => $response['transactionId'],
                    'expired' => $response['expired'],
                    'va_fee' => $fee,
                ]);

                $namaDonasi = Program::where('program_id', $request->id)->first();
                $zonaWaktu = $user->waktu_indonesia;
                // dd($zonaWaktu);
                $totalBayarFormatted = 'Rp. ' . number_format($totalBayar, 0, ',', '.');
                $tanggal = Carbon::now($zonaWaktu);
                $nama =  $request->donasi_name ?? $user->student_full_name;
                $pesan = <<<EOT
    Bagian Administrasi  $nama_sekolah

    Assalamualaikum warahmatullahi wabarakatuh,

    Tanggal: {$tanggal}

    Yth. Ayah/Bunda dari ananda {$nama} ,

    Terima kasih telah menggunakan aplikasi ePesantren. Berikut detail pembayaran donasi yang perlu dilakukan:

    ID transaksi    :
    Total                 : {$totalBayarFormatted}
    Nomor VA        : {$response['va_number']}
    Bank                 : {$response['bank']}
    Nama Donasi : {$namaDonasi->program_name}

    Mohon lakukan pembayaran sebelum:
    {$response['expired']}

    Pembayaran dapat dilakukan melalui aplikasi mobile atau metode lain yang tersedia.
    Total pembayaran: {$totalBayarFormatted}

    Hormat kami,
    Bagian Administrasi
    EOT;

                $whatsappService = new WhatsappServicePembayaran();
                // $nowa = $user->student_parent_phone;
                $nowa = $request->donasi_hp ?? $user->student_parent_phone;
                $whatsappService->kirimPesan($nowa, $pesan);

                return response()->json([
                    //'is_correct' => $response['is_correct'],
                    'is_correct' => true,
                    'bayar_via' => $response['senderBankType'],
                    'bank' => $response['bank'],
                    'label' => $response['label'],
                    'va' => $response['va_number'],
                    //'nominal' => $totalBayar + $fee,
                    'nominal' => (int) $nominal,
                    'total_bayar' => (int) $totalBayar,
                    'fee' => (int) $fee,
                    'expired' => $response['expired'],
                    'carabayar' => [
                        'metode' => $response['label'] . '|' . $response['bank'],
                        'bayar' => $caraBayar->cara_bayar,
                        'logo' => 'https://mobile.epesantren.co.id/walsan/assets/logo_bank/' . $caraBayar->logo,
                    ],
                    'payment_url' => $response['payment_url'],

                    'message' => 'Data anda valid'
                ], 200);
            } catch (\Throwable $th) {
                return response()->json([
                    'is_correct' => false,
                    'message' => $th->getMessage(),
                    //'error' => $th->getMessage()
                ], 500);
            }
        } else {
            //  dd('ini ipaymu');
            $secret = 'SANDBOX5842A926-580F-4C08-9D84-B576859A95B4';
            $va = '0000001233640003';
            $url = 'https://sandbox.ipaymu.com/api/v2/payment/direct';
            //$notifyUrl = 'https://mobile.epesantren.co.id/walsan/notif_ipaymuall.php';
            $notifyUrl = 'https://api-epesantren-wali.ninetale.my.id/api/ipaymu/callback';
            $user = auth()->user();
            $token = JWTAuth::parseToken();

            $user = auth()->user();
            $token = JWTAuth::parseToken();

            // Get the token payload
            $claims = $token->getPayload();

            $kodePondok = $claims->get('kode_sekolah');
            $claims = $token->getPayload();
            $kode_sekolah = $claims->get('kode_sekolah');
            $nama_sekolah = $claims->get('schoolName');
            $PaymentGateway = $claims->get('payment');
            // dd($PaymentGateway);
            if ($PaymentGateway != 'AKTIF') {
                return response()->json([
                    'message' => 'Anda tidak terdaftar dalam payment Gateway'
                ], 409);
            }
            $validator = Validator::make($request->all(), [
                //donasi
                'id' => 'required',
                'donasi_ref_id' => 'nullable',
                'donasi_nominal' => 'required',
                'donasi_status' => 'nullable',
                'donasi_name' => 'nullable',
                'donasi_alamat' => 'nullable',
                'donasi_hp' => 'nullable',
                'donasi_datetime' => 'nullable',

                //flip_donasi
                'kode_pondok' => 'nullable',
                'student_id' => 'nullable',
                'noref' => 'nullable',
                'tanggal' => 'nullable',
                'status' => 'nullable',
                'dibayar' => 'nullable',
                'va_no' => 'nullable',
                'va_nama' => 'nullable',
                'va_channel' => 'nullable',
                'va_bank' => 'nullable',
                'transactionId' => 'nullable',
                'va_fee' => 'nullable',
                'create_at' => 'nullable',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'is_correct' => false,
                    'message' => 'error',
                    'data' => $validator->errors()
                ], 400);
            }

            // if ($request->nominal > 50000000) {
            //     return response()->json([
            //         'message' => 'Uang melebihi batas pembayaran dibawah 50 Juta (dengan fee Rp4.500)',
            //     ], 466); // Status code 466
            // }

            $phone = $user->student_parent_phone;
            if ($phone == NULL) {
                $kode = '08';
                $rand = rand(1000000000, 9999999999);
                $phone = $kode . $rand;
            } else {
                $phone = str_replace('+62', '0', $phone);
            }
            //dd($phone);
            $nominal = $request->donasi_nominal;
            //    dd($nominal);
            $donasiName = $user->student_name_of_mother;
            $alamat = $user->student_address;
            $hp = $user->student_parent_phone;
            $ref = idate('U');
            $lokasi = $user->waktu_indoesia;
            $donasi = Donasi::create([
                'program_program_id' => $request->id,
                'donasi_nominal' => $request->donasi_nominal,
                'donasi_status' => 0,
                'donasi_ref_id' => $ref,
                'donasi_name' => $request->donasi_name,
                'donasi_alamat' => $request->donasi_alamat,
                'donasi_hp' => $request->donasi_hp,
                'donasi_datetime' => now($lokasi),
            ]);

            $IpaymuDonasi = IpaymuDonasi::create([
                'kode_pondok' => $kodePondok,
                //'student_id' => $user->student_id,
                'noref' => $ref,
                'tanggal' =>  now($lokasi),
                'nominal' => $request->donasi_nominal,
                'status' => 'PENDING',
                'dibayar' => 0,
                'va_no' => 'null',
                'va_nama' => 'null',
                'va_channel' => 'null',
                'va_bank' => 'null',
                'transactionId' => 'null',
                'va_fee' => 'null',
                'create_at' => now($lokasi),
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'is_correct' => false,
                    'message' => 'error',
                    'data' => $validator->errors()
                ], 400);
            }


            // if ($query) {
            $nis = $user->student_nis;
            $request->validate([
                'payment_channel' => 'required',
            ]);

            $nominal = $request->donasi_nominal;
            $payment_channel = $request->payment_channel;
            $caraBayar = data_ipaymu_channel::where('payment_channel', $payment_channel)->first();
            // dd($caraBayar);
            $fee = $caraBayar->fee;
            $totalBayar = $nominal + $fee;

            $kodeBayar = 3;
            $payment = explode("|", $caraBayar->payment_channel);
            // dd($payment);
            $method       = 'POST';
            $paymentMethod = $payment[0];
            $paymentChannel = $payment[1];
            $kodePembayaran = 3;

            $student_full_name = $user->student_full_name;

            $nameEmail = explode(" ", $user->student_full_name);
            $firstName = $nameEmail[0];
            // $lastName = $nameEmail[1]
            $lastName = $nameEmail[1] ?? '';
            $email = $this->RemoveSpecialChar($firstName) . $this->RemoveSpecialChar($lastName) . '@epesantren.co.id';
            // dd($paymentMethod);
            //  dd($phone);
            $body['name']    = $student_full_name;
            $body['email']   = $email;
            $body['phone']   = $phone;
            $body['amount']  = $request->donasi_nominal + $fee;
            //  dd($body['amount']);
            $body['notifyUrl']   = $notifyUrl;
            $body['expired']   = '6';
            $body['expiredType']   = 'hours';
            $body['comments']   = 'ePesantren';
            $body['referenceId']   = $kodePondok . '|' . $user->student_nis . '|' . $kodePembayaran . '|' . $request->id;
            $body['paymentMethod']  = $paymentMethod;
            $body['paymentChannel']   = $paymentChannel;
            //dd($body['paymentChannel']);
            $body['description']   = 'ePesantren';

            //dd($body['phone']);

            $jsonBody     = json_encode($body, JSON_UNESCAPED_SLASHES);
            //dd($jsonBody);
            // echo '<br>'. $jsonBody;
            $requestBody  = strtolower(hash('sha256', $jsonBody));
            $stringToSign = strtoupper($method) . ':' . $va . ':' . $requestBody . ':' . $secret;
            //dd($stringToSign);
            $signature    = hash_hmac('sha256', $stringToSign, $secret);

            //  dd($signature);
            $timestamp    = Date('YmdHis');
            try {
                // Melakukan request ke API iPaymu menggunakan Laravel HTTP Client
                $response = Http::withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'va' => $va,
                    'signature' => $signature,
                    'timestamp' => $timestamp
                ])->post($url, $body);

                $responseData = $response->json();
                //   dd($responseData);
                // Customize the response
                $customResponse = [
                    'is_correct' => true,
                    'bayar_via' => $responseData['Data']['Via'],
                    'bank' =>  $responseData['Data']['Channel'],
                    'label' => $responseData['Data']['Via'],
                    'va' => $responseData['Data']['PaymentNo'],
                    'nominal' => $responseData['Data']['SubTotal'] - $fee,
                    'total_bayar' => $request->donasi_nominal + $fee,
                    'fee' => $fee,
                    'expired' => $responseData['Data']['Expired'],
                    'carabayar' => [
                        'metode' => $caraBayar->payment_channel,
                        'bayar' => $caraBayar->cara_bayar,
                        'logo' => 'https://mobile.epesantren.co.id/walsan/assets/logo_bank/' . $caraBayar->logo,
                    ],
                    'payment_url' => '',
                    'message' => 'Data anda valid'

                ];
                $unit = $user->major->majors_short_name;
                $id = $user->student_id;
                $nis = $user->student_nis;
                $tanggal = Carbon::now();
                $tanggal_hari_ini = $tanggal->format('d');
                $bulan_hari_ini = $tanggal->format('m');
                $tahun_hari_ini = $tanggal->format('y');

                $query = IpaymuDonasi::where('noref', $ref)->update([
                    'va_no' => $responseData['Data']['PaymentNo'],
                    'va_channel' => $caraBayar->payment_channel,
                    'va_bank' => $responseData['Data']['Channel'],
                    'va_nama' => $responseData['Data']['PaymentName'],
                    'va_transactionId' => $responseData['Data']['TransactionId'],
                    'va_fee' => $fee,
                    'expired' => $responseData['Data']['Expired'],
                ]);
                //   dd($query);

                return response()->json($customResponse);
            } catch (\Exception $e) {
                return response()->json([
                    'message' => 'An error occurred',
                    'error' => $e->getMessage()
                ], 500);
            }
            //    dd('ini donasi ipaymu');
        }
    }

    // private function getFlipConfig()
    // {
    //     // Mendapatkan mode Flip (test atau live) dari variabel environment
    //     $flipMode = env('FLIP_MODE', 'test');

    //     // Menentukan API key dan URL berdasarkan mode
    //     if ($flipMode === 'live') {
    //         return [
    //             'secret_key' => env('FLIP_API_KEY_LIVE'),
    //             'url' => env('FLIP_API_URL_LIVE'),
    //         ];
    //     }

    //     return [
    //         'secret_key' => env('FLIP_API_KEY_TEST'),
    //         'url' => env('FLIP_API_URL_TEST'),
    //     ];
    // }
    private function getFlipConfig()
    {
        // Mendapatkan mode Flip (test atau live) dari variabel environment
        $flipMode = Setting::where('setting_name', 'mode_flip')->first();
        $setting = $flipMode->setting_value;
        if ($setting == 'my') {
            $flipMode = 'live';
        } else {
            $flipMode = 'test';
        }

        // Mengambil API key dari tabel setting berdasarkan mode
        if ($flipMode === 'live') {
            $apiKey = Setting::where('setting_name', 'api_secret_key')->first();
            $getApiKey = $apiKey ? $apiKey->setting_value : null;
            $url = 'https://bigflip.id/api/v2';
        } else {
            $apiKey = Setting::where('setting_name', 'api_secret_key_test')->first();
            //   dd($apiKey);
            $getApiKey = $apiKey ? $apiKey->setting_value : null;
            $url = 'https://bigflip.id/big_sandbox_api/v2';
            //  dd($url);
        }

        // Pastikan API key ditemukan, jika tidak, lempar error
        if (!$getApiKey) {
            throw new \Exception('API secret key not found in the settings table.');
        }

        return [
            'secret_key' => $getApiKey,
            'url' => $url,
        ];
    }

    // private function getFlipConfig()
    // {
    //     // Mendapatkan mode Flip (test atau live) dari variabel environment
    //     $flipMode = env('FLIP_MODE', 'test');

    //     // Mengambil API key dari tabel setting berdasarkan mode
    //     if ($flipMode === 'live') {
    //         $apiKey = Setting::where('setting_name', 'api_secret_key')->first();
    //         $getApiKey = $apiKey ? $apiKey->setting_value : null;
    //         $url = env('FLIP_API_URL_LIVE');
    //     } else {
    //         $apiKey = Setting::where('setting_name', 'api_secret_key_test')->first();
    //         $getApiKey = $apiKey ? $apiKey->setting_value : null;
    //         $url = env('FLIP_API_URL_TEST');
    //     }

    //     // Pastikan API key ditemukan, jika tidak, lempar error
    //     if (!$getApiKey) {
    //         throw new \Exception('API secret key not found in the settings table.');
    //     }

    //     return [
    //         'secret_key' => $getApiKey,
    //         'url' => $url,
    //     ];
    // }

    private function  createPaymentToFlip($payload, $flipConfig, $ref)
    {
        try {
            $response = Http::withBasicAuth($flipConfig['secret_key'], '')
                ->asForm()
                ->post($flipConfig['url'] . '/pwf/bill', $payload);

            $data = $response->json();

            if (!$response->successful() || !isset($data['bill_payment'])) {
                throw new \Exception('Gagal membuat Virtual Account. Silakan coba lagi.');
            }

            $transactionalId = $data['link_id'];
            if ($transactionalId) {
                FlipDonasi::where('noref', $ref)->update(['transactionId' => $transactionalId]);
            }

            if ($data) {
                $vaNumber = $data['bill_payment']['receiver_bank_account']['account_number'];
                $paymentUrl = $data['payment_url'];
                $label = $data['bill_payment']['receiver_bank_account']['account_type'];
                $bank = $data['bill_payment']['sender_bank'];
                $senderBankType = $data['bill_payment']['sender_bank_type'];
                $expiredDate = $data['expired_date'];
                $user_id = $payload['user_id'];

                // Mengembalikan respons sukses dengan VA number dan payment URL
                return [
                    'is_correct' => 'success',
                    'payment_url' => $paymentUrl,
                    'senderBankType' => $senderBankType,
                    'va_number' => $vaNumber,
                    'bank' => $bank,
                    'label' => $label,
                    'bayar_via' => $senderBankType,
                    'expired' => $expiredDate,
                    'student_id' => $user_id
                ];
            }
        } catch (\Throwable $th) {
            return [
                'is_correct' => 'error',
                'message' => $th->getMessage(),
                'va_generated' => false, // Tandakan bahwa VA tidak tergenerate
                'flip_api_response' => $data ?? null, // Sertakan respons API jika ada
            ];
        }
    }

    function RemoveSpecialChar($str)
    {

        $res = preg_replace('/[^a-zA-Z0-9_ -]/s', '', $str);
        return $res;
    }
}
