<?php

namespace App\Http\Controllers;

use DateTime;
use Carbon\Carbon;
use App\Models\major;
use App\Models\Setting;
use App\Models\Tabungan;
use App\Models\BniConfig;
use App\Models\FlipDonasi;
use App\Models\FlipChannel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\data_ipaymu_channel;
use App\Services\WhatsappServiceOtp;
use Illuminate\Support\Facades\Http;
use App\Models\FlipTransaksiTabungan;
use Illuminate\Support\Facades\Schema;
use App\Models\IpaymuTransaksiTabungan;
use Illuminate\Support\Facades\Validator;
use App\Services\WhatsappServicePembayaran;

class TabunganController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $totalDebit = intval(Tabungan::where('banking_student_id', $user->student_id)->sum('banking_debit'));
        $totalKredit = intval(Tabungan::where('banking_student_id', $user->student_id)->sum('banking_kredit'));
        $tabunganAnda = $totalDebit - $totalKredit;
        $data = Tabungan::where('banking_student_id', $user->student_id)->get()->reverse()->map(function ($item) {
            return [
                //'banking_id' => $item->banking_id,
                'banking_date' => $item->banking_date,
                'debit' => $item->banking_debit,
                'kredit' => $item->banking_kredit,
                'catatan' => $item->banking_note

            ];
        })->values();
        // if ($data) {
        return response()->json([
            'is_correct' => true,
            'nama' => $user->student_full_name,
            "class_name" => $user->kelas->class_name,
            'message' => 'Data anda valid',
            'saldo' => $tabunganAnda,
            'pemasukan' => $totalDebit,
            'pengeluaran' => $totalKredit,
            'laporan' => $data
        ], 200);
        // } else {
        //     return response()->json([
        //         'is_correct' => false,
        //         'message' => 'data not found'
        //     ], 400);
        // }
    }

    public function riwayatTransaksi(Request $request, $start_date = null, $end_date = null)
    {
        $setting = Setting::where('setting_name', 'api_secret_key')->first();
        if ($setting) {
            $user = auth()->user();
            if (!$start_date) {
                $start_date = $request->input('start_date');
            }
            if (!$end_date) {
                $end_date = $request->input('end_date');
            }

            //inisiasi quey nya
            $query = FlipTransaksiTabungan::where('student_id', $user->student_id)
                ->whereIn('status', ['FAILED', 'SUCCESSFUL', 'PENDING']);

            //filter berdasarkan parameter yang diisi
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
        } else {
            // dd('coba');
            $user = auth()->user();
            if (!$start_date) {
                $start_date = $request->input('start_date');
            }
            if (!$end_date) {
                $end_date = $request->input('end_date');
            }

            //inisiasi quey nya
            $query = IpaymuTransaksiTabungan::where('student_id', $user->student_id);

            //filter berdasarkan parameter yang diisi
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
    }

    public function detailRiwayat($id)
    {
        // First debug point
        // dd('Initial check');

        $setting = Setting::where('setting_name', 'api_secret_key')->first();

        // Debug setting value
        //   dd('Setting value:', $setting);

        if ($setting) {
            //dd('ccoba');
            $user = auth()->user();
            $data = FlipTransaksiTabungan::where('student_id', $user->student_id)
                ->where('id_transaksi', $id)
                ->first();

            // Debug data after first query
            // dd('Data after first query:', $data);

            if (!$data) {
                return response()->json([
                    'is_correct' => false,
                    'message' => 'Transaction not found'
                ], 404);
            }

            $fee = $data->va_fee;
            $bank = strtoupper($data->va_bank);
            $fotoBank = FlipChannel::where('kode', $bank)->first();

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
                'foto' => 'https://mobile.epesantren.co.id/walsan/assets/logo_bank/' . ($fotoBank ? $fotoBank->logo : '')
            ], 200);
        }

        // If setting is null
        $user = auth()->user();
        $data = IpaymuTransaksiTabungan::where('student_id', $user->student_id)
            ->where('id_transaksi', $id)
            ->first();

        // Debug data after first query
        // dd('Data after first query:', $data);

        if (!$data) {
            return response()->json([
                'is_correct' => false,
                'message' => 'Transaction not found'
            ], 404);
        }

        $fee = $data->va_fee;
        $bank = strtoupper($data->va_bank);
        $fotoBank = data_ipaymu_channel::where('kode', $bank)->first();

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
            'foto' => 'https://mobile.epesantren.co.id/walsan/assets/logo_bank/' . ($fotoBank ? $fotoBank->logo : '')
        ], 200);
    }

    public function processPayment(Request $request)
    {
        $setting = Setting::where('setting_name', 'api_secret_key')->first();
        $currentConnection = DB::connection()->getDatabaseName();

        if ($currentConnection == 'adminsek_demo1') {
            $student = auth()->user();
            $token = JWTAuth::parseToken();
            $claims = $token->getPayload();
            $kode_sekolah = $claims->get('kode_sekolah');
            // dd($kode_sekolah);
            $waktu = $claims->get('waktu_idonesia');
            //dd($waktu);
            $major = major::where('majors_id', $student->majors_majors_id)->first();
            $majorName = $major->majors_short_name;

            $validator = Validator::make($request->all(), [
                'kode_pondok' => 'nullable',
                'noref' => 'nullable',
                'tanggal' => 'nullable',
                'student_id' => 'nullable',
                'nominal' => 'required|numeric',
                'catatan' => 'required',
                'status' => 'nullable',
                'di_bayar' => 'nullable',
                'va_no' => 'nullable',
                'va_nama' => 'nullable',
                'va_channel' => 'nullable',
                'trasactionId' => 'nullable',
                'Expired' => 'nullable',
                'va_fee' => 'nullable',
                'create_at' => 'nullable',
                'va_channel' => 'required'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'is_correct' => false,
                    'message' => $validator->error()
                ], 400);
            };

            if ($request->nominal > 50000000) {
                return response()->json([
                    'message' => 'Uang melebihi batas pembayaran dibawah 50 Juta (dengan fee Rp4.500)',
                ], 466); // Status code 466
            }

            $nameEmail = explode(" ", $student->student_full_name);
            $firstName = $nameEmail[0];
            $lastName = $nameEmail[1] ?? '';

            $namaemail = $this->RemoveSpecialChar($firstName) . $this->RemoveSpecialChar($lastName) . '@adminsekolah.net';
            $nominal = FlipTransaksiTabungan::where('id_transaksi', $request->id_transaksi)->first();
            //   dd($nominal->nominal);
            $like = 'TAB' . str_replace(" ", "", $majorName . $student->student_nis);
            $idMajors = $major;
            // $noref = Kas::getNoref($like, $idMajors);
            $noref =  time();
            $paymentNoref = $like . $noref;
            $kodeBayar = 2;
            $catatan = $request->catatan;
            // dd($request->nominal);
            $data = [
                "secret_key" => 'fa871cfdec63afb05ad61b51f19722d8', // Secret key dari BNI
                "client_id" => 27059, // Client ID dari Flip
                "customer_name" => $student->student_full_name,
                "customer_email" => $namaemail,
                "description" => 'Pembayaran atas nama ' . $student->student_full_name . '|' . $student->student_nis . ' |' . $kode_sekolah . '|' . $catatan . '|' . $kodeBayar . '|' . 'Tabungan',
                //  'description' => 'bayar',
                "datetime_expired" => now()->addDays(1)->format('Y-m-d H:i:s'), // Contoh: expired 1 hari dari sekarang
                "trx_amount" => $request->nominal,
                "trx_id" => $paymentNoref, // ID transaksi
                "type" => "createbilling",
                "virtual_account" => '9882705900000023', // Nomor virtual account
                "billing_type" => 'c',
            ];

            $dataBank = FlipChannel::where('bank', 'BNI')->first();
            $namaBank = $dataBank->bank;
            $metode = $dataBank->payment_channel;
            $bayar = $dataBank->cara_bayar;
            $logo = $dataBank->logo;

            // dd($namaBank);

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post('https://bni.indoweb.id/api/create_billing', $data);

            //   dd($response->json());
            $waktuAsli = Carbon::now('Asia/jakarta')->format('Y-m-d H:i:s');
            //  dd($waktuAsli);
            //dd($waktu);



            if ($waktu == 'WIT') {
                $waktuAsli = Carbon::now('Asia/Makassar')->format('Y-m-d H:i:s');
            }
            if ($waktu == 'WITA') {
                $waktuAsli = Carbon::now('Asia/Jayapura')->format('Y-m-d H:i:s');
            }
            $responseData = $response->json();
            //     dd($responseData);
            if (isset($responseData['data']['virtual_account'])) {
                FlipTransaksiTabungan::create([
                    'kode_pondok' => $kode_sekolah,
                    'noref' => $paymentNoref,
                    'tanggal' =>  now($waktu),
                    'student_id' => $student->student_id,
                    'va_no' => $responseData['data']['virtual_account'],
                    'va_channel' => 'virtual_account',
                    'status' => 'PENDING',
                    'nominal' => $request->nominal,
                    'tanggal' => $waktuAsli,
                    'va_fee' => 0,
                    'expired' => 'Belum',
                    'va_bank' => 'bni',
                ]);

                $created_date  = date("Y-m-d H:i:s");
                $desc = 'Pembayaran sekolah A/N ' . $student->student_full_name;
                $trxId = BniConfig::where('mode', 'DEV')->first();

                $curdate = new DateTime();
                $curdate->modify('+6 hours');
                $datetime_expired = $curdate->format('Y-m-d\TH:i:sP');
                //memasukkan ke bni trx
                $BniTrx = [
                    'type' => 'createbilling',
                    'noref' => $paymentNoref,
                    'trx_amount' => $request->nominal,
                    'trx_id' => 'TRX' . date('ymdhis'),
                    'client_id' => $trxId->client_id,
                    'description' => $desc,
                    'datetime_expired' => $datetime_expired,
                    'virtual_account' => $responseData['data']['virtual_account'],
                    'customer_name' => $student->student_full_name,
                    'payment_amount' => 0,
                    'cumulative_payment_amount' => 0,
                    'billing_type' => 'c',
                    'created_at' => $created_date,
                    'trx_status' => 'PENDING',
                    'student_id' => $student->student_id,
                    'payment_ntb' => 0
                ];

                //BniTrx::create($BniTrx);
                // Jika ada, kembalikan custom response
                return response()->json([
                    'is_correct' => true,
                    'bayar_via' => 'virtual_account',
                    'bank' => $namaBank,
                    'label' => 'virtual_account',
                    'va' => $responseData['data']['virtual_account'], // Ambil dari response
                    'nominal' => (int)$request->nominal,
                    'total_bayar' => (int)$request->nominal,
                    'fee' => 0,
                    'expired' => 'belum',
                    'carabayar' => [
                        'metode' => $metode,
                        'bayar' => $bayar,
                        'logo' => 'https://mobile.epesantren.co.id/walsan/assets/logo_bank/' . $logo
                    ],
                    'payment_url' => 'belum',
                    'message' => 'Data anda valid'
                ], 200);
            } else {
                // Jika key 'data' atau 'virtual_account' tidak ditemukan
                return response()->json([
                    'message' => 'Struktur response API tidak valid. Virtual account tidak ditemukan.'
                ], 400);
            }
        }

        if ($setting) {
            try {
                $user = auth()->user();
                $token = JWTAuth::parseToken();

                // Get the token payload
                $claims = $token->getPayload();

                $kode_pondok = $claims->get('kode_sekolah');
                $nama_sekolah = $claims->get('schoolName');
                $PaymentGateway = $claims->get('payment');
                // dd($PaymentGateway);
                if ($PaymentGateway != 'AKTIF') {
                    return response()->json([
                        'message' => 'Anda tidak terdaftar dalam payment Gateway'
                    ], 409);
                }
                // $idTransaksi = $request->input('id_transaksi');
                //  $maxs = env('flip_mode')

                $validator = Validator::make($request->all(), [
                    'kode_pondok' => 'nullable',
                    'noref' => 'nullable',
                    'tanggal' => 'nullable',
                    'student_id' => 'nullable',
                    'nominal' => 'required|numeric',
                    'catatan' => 'required',
                    'status' => 'nullable',
                    'di_bayar' => 'nullable',
                    'va_no' => 'nullable',
                    'va_nama' => 'nullable',
                    'va_channel' => 'nullable',
                    'trasactionId' => 'nullable',
                    'Expired' => 'nullable',
                    'va_fee' => 'nullable',
                    'create_at' => 'nullable',
                    'va_channel' => 'required'
                ]);

                if ($validator->fails()) {
                    return response()->json([
                        'is_correct' => false,
                        'message' => $validator->error()
                    ], 400);
                };

                if ($request->nominal > 50000000) {
                    return response()->json([
                        'message' => 'Uang melebihi batas pembayaran dibawah 50 Juta (dengan fee Rp4.500)',
                    ], 466); // Status code 466
                }
                $payment_channel = $request->va_channel;
                $payment_channel_parts = explode('|', $payment_channel);
                $payment_method = $payment_channel_parts[1] ?? null;

                $caraBayar = FlipChannel::where('payment_channel', $payment_channel)->first();
                // $caraBayar = FlipChannel::where('payment_channel', $payment_channel)->first() ?? null;
                //Sdd($caraBayar);
                $fee = $caraBayar->fee ?? null;

                $nameEmail = explode(" ", $user->student_full_name);
                $firstName = $nameEmail[0];
                // $lastName = $nameEmail[1]
                $lastName = $nameEmail[1] ?? '';
                $email = $this->RemoveSpecialChar($firstName) . $this->RemoveSpecialChar($lastName) . '@epesantren.co.id';

                $senderBank = strtolower($payment_method);
                $paymentMethod = $payment_channel_parts[0];
                $nis = $user->student_nis;
                $kodeBayar = 2;
                //  dd($paymentMethod);
                // id transaksi
                //$idTransaksi = 'Tabungan';
                $catatan = $request->catatan;
                $payload = [
                    'title' => 'Pembayaran atas nama ' .  $user->student_full_name . '|' . $nis . ' | ' . $kode_pondok . '|' . $catatan . '|' . $kodeBayar . '|' . 'Tabungan',
                    'amount' => $request->nominal + $fee,
                    'total_bayar' => $request->nominal + $fee,
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
                    // 'user_id' => "1"
                    'catatan' => $catatan = $request->catatan
                ];
                $catatan = $request->catatan;

                // dd($catatan);
                $unit = $user->major->majors_short_name;
                $id = $user->student_id;
                $nis = $user->student_nis;
                $tanggal = Carbon::now();
                $tanggal_hari_ini = $tanggal->format('d');
                $bulan_hari_ini = $tanggal->format('m');
                $tahun_hari_ini = $tanggal->format('y');

                //noref
                $noref = "TAB" . $unit . $id . $nis . $tanggal_hari_ini . $bulan_hari_ini . $tahun_hari_ini;
                // dd($noref);

                $nominal = $request->nominal + $fee;

                // $response = response()->json([
                //     "message" => "anjay mabar"
                // ]);
                // $response->setStatusCode(466, "Uang melebihi batas pembayaran dibawah 50 Juta (dengan fee Rp4.500)");

                // if ($nominal > 50000000) {
                //     return $response;
                // }

                // dd($nominal);

                $flipConfig = $this->getFlipConfig();
                //  dd($payload);
                $response = $this->createPaymentToFlip($payload, $flipConfig, $kode_pondok, $user, $nominal, $fee, $catatan, $noref, $nama_sekolah);
                if ($response['is_correct'] === 'error') {
                    throw new \Exception("Gagal membuat Virtual Account. Silakan coba lagi.");
                }

                return response()->json([
                    'is_correct' => true,
                    'bayar_via' => $response['bayar_via'],
                    'bank' => $response['bank'],
                    'label' => $response['label'],
                    'va' => $response['va_number'],
                    //'nomor_referensi' => $noref,
                    'catatan' => $catatan,
                    'nominal' => (int)$request->nominal,
                    'total_bayar' => $nominal,
                    'fee' => $fee,
                    'payment_url' => $response['payment_url'],
                    //  'qris' => $response['qris'],
                    'expired' => $response['expired'],
                    'carabayar' => [
                        'metode' => $response['bayar_via'] . '|' . $response['bank'],
                        'bayar' => $caraBayar->cara_bayar,
                        'logo' => "https://mobile.epesantren.co.id/walsan/assets/logo_bank/" . $caraBayar->logo
                    ],
                    'message' => 'Data anda valid',
                ]);
            } catch (\Throwable $th) {
                return response()->json([
                    'is_correct' => false,
                    'message' => $th->getMessage(),
                    //'error' => $th->getMessage()
                ], 500);
            }
        } else {
            //mode test ipaymu development
            // $secret = 'SANDBOX5842A926-580F-4C08-9D84-B576859A95B4';
            // $va = '0000001233640003';

            //mode production ipaymu
            $getsecret = Setting::where('setting_name', 'key_ipaymu')->first();
            $secret = $getsecret->setting_value;
            $getVa = Setting::where('setting_name', 'va_ipaymu')->first();
            $va = $getVa->setting_value;
            // dd($va);
            //url testing
            //$url = 'https://sandbox.ipaymu.com/api/v2/payment/direct';

            //url production
            $url = 'https://my.ipaymu.com/api/v2/payment/direct';

            //$notifyUrl = 'https://mobile.epesantren.co.id/walsan/notif_ipaymuall.php';
            $notifyUrl = 'https://api.adminsekolah.net/api/ipaymu/callback';
            $user = auth()->user();
            $token = JWTAuth::parseToken();

            // Get the token payload
            $claims = $token->getPayload();

            $kode_pondok = $claims->get('kode_sekolah');
            $nama_sekolah = $claims->get('schoolName');
            $PaymentGateway = $claims->get('payment');
            // dd($PaymentGateway);
            if ($PaymentGateway != 'AKTIF') {
                return response()->json([
                    'message' => 'Anda tidak terdaftar dalam payment Gateway'
                ], 409);
            }

            $validator = Validator::make($request->all(), [
                'kode_pondok' => 'nullable',
                'noref' => 'nullable',
                'tanggal' => 'nullable',
                'student_id' => 'nullable',
                'nominal' => 'required|numeric',
                'catatan' => 'required',
                'status' => 'nullable',
                'di_bayar' => 'nullable',
                'va_no' => 'nullable',
                'va_nama' => 'nullable',
                'va_channel' => 'nullable',
                'trasactionId' => 'nullable',
                'Expired' => 'nullable',
                'va_fee' => 'nullable',
                'create_at' => 'nullable',
                'va_channel' => 'required'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'is_correct' => false,
                    'message' => $validator->error()
                ], 400);
            };

            if ($request->nominal > 50000000) {
                return response()->json([
                    'message' => 'Uang melebihi batas pembayaran dibawah 50 Juta (dengan fee Rp4.500)',
                ], 466); // Status code 466
            }
            $phone = $user->student_parent_phone;
            if ($phone == NULL) {
                $kode = '08';
                $rand = rand(1000000000, 9999999999);
                $phone = $kode . $rand;
            } else {
                $phone = str_replace('+62', '0', $phone);
            }
            //dd($phone);

            $payment_channel = $request->va_channel;
            $payment_channel_parts = explode('|', $payment_channel);
            $payment_method = $payment_channel_parts[1] ?? null;

            $caraBayar = data_ipaymu_channel::where('payment_channel', $payment_channel)->first();
            //dd($caraBayar);
            $fee = $caraBayar->fee ?? null;

            $nameEmail = explode(" ", $user->student_full_name);
            $firstName = $nameEmail[0];
            // $lastName = $nameEmail[1]
            $lastName = $nameEmail[1] ?? '';
            $email = $this->RemoveSpecialChar($firstName) . $this->RemoveSpecialChar($lastName) . '@adminsekolah.net';

            $senderBank = strtolower($payment_method);
            $paymentMethod = $payment_channel_parts[0];
            $nis = $user->student_nis;
            $kodeBayar = 2;

            $catatan = $request->catatan;
            $payment_channel = data_ipaymu_channel::where('payment_channel', $request->va_channel)->first();
            //   dd($payment_channel);
            $payment = explode("|", $payment_channel->payment_channel);
            //  dd($payment);
            $bank_fee = $payment_channel->fee;

            $method       = 'POST';
            $paymentMethod = $payment[0];
            $paymentChannel = $payment[1];
            $kodePembayaran = 2;

            $student_full_name = $user->student_full_name;
            $kodeProgram = '';
            // dd($paymentMethod);
            //  dd($phone);
            $body['name']    = $student_full_name;
            $body['email']   = $email;
            $body['phone']   = $phone;
            $body['amount']  = $request->nominal + $bank_fee;
            $body['notifyUrl']   = $notifyUrl;
            $body['expired']   = '6';
            $body['expiredType']   = 'hours';
            $body['comments']   = 'ePesantren';
            $body['referenceId']   = $kode_pondok . '|' . $user->student_nis . '|' . $kodePembayaran . '|' . $kodeProgram . '|' . $catatan;
            $body['paymentMethod']  = $paymentMethod;
            $body['paymentChannel']   = $paymentChannel;
            $body['description']   = 'ePesantren';

            //dd($body['phone']);

            $jsonBody     = json_encode($body, JSON_UNESCAPED_SLASHES);
            // dd($jsonBody);
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
                //  dd($responseData);
                // Customize the response
                $customResponse = [
                    'is_correct' => true,
                    'bayar_via' => $responseData['Data']['Via'],
                    'bank' =>  $responseData['Data']['Channel'],
                    'label' => $responseData['Data']['Via'],
                    'va' => $responseData['Data']['PaymentNo'],
                    'nominal' => $responseData['Data']['SubTotal'] - $bank_fee,
                    'total_bayar' => $request->nominal + $bank_fee,
                    'fee' => $bank_fee,
                    'expired' => $responseData['Data']['Expired'],
                    'carabayar' => [
                        'metode' => $payment_channel->payment_channel,
                        'bayar' => $payment_channel->cara_bayar,
                        'logo' => 'https://mobile.epesantren.co.id/walsan/assets/logo_bank/' . $payment_channel->logo,
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

                //noref
                $noref = "TAB" . $unit . $id . $nis . $tanggal_hari_ini . $bulan_hari_ini . $tahun_hari_ini;
                //dd($responseData['Data']['TransactionId']);
                $data = [
                    'kode_pondok' => $kode_pondok,
                    'noref' => $noref,
                    'tanggal' => now($user->waktu_indonesia),
                    'student_id' => $user->student_id,
                    'nominal' => $request->nominal,
                    'status' => 'PENDING',
                    'di_bayar' => 0,
                    'va_no' => $responseData['Data']['PaymentNo'],
                    'va_nama' => $responseData['Data']['PaymentName'],
                    'va_channel' => $payment_channel->payment_channel,
                    'va_bank' => $responseData['Data']['Channel'],
                    'va_transactionId' => $responseData['Data']['TransactionId'],
                    'Expired' => $responseData['Data']['Expired'],
                    'va_fee' => $bank_fee,
                    'create_at' => now($user->waktu_indonesia)
                ];

                IpaymuTransaksiTabungan::create($data);

                return response()->json($customResponse);
            } catch (\Exception $e) {
                return response()->json([
                    'message' => 'An error occurred',
                    'error' => $e->getMessage()
                ], 500);
            }

            // $idTransaksi = $request->input('id_transaksi');
            //  $maxs = env('flip_mode')


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
            // dd($apiKey);
            $getApiKey = $apiKey ? $apiKey->setting_value : null;
            // dd($getApiKey);
            $url = 'https://bigflip.id/api/v2';
        } else {
            $apiKey = Setting::where('setting_name', 'api_secret_key_test')->first();
            $getApiKey = $apiKey ? $apiKey->setting_value : null;
            // $apiKey = env('FLIP_API_KEY_TEST');
            // $getApiKey = $apiKey;
            $url = 'https://bigflip.id/big_sandbox_api/v2';
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

    private function createPaymentToFlip($payload, $flipConfig, $kode_pondok, $user, $nominal, $fee, $catatan, $noref, $nama_sekolah)
    {
        try {
            // Mengirim permintaan POST ke Flip API
            $response = Http::withBasicAuth($flipConfig['secret_key'], '')
                ->asForm()
                ->post($flipConfig['url'] . '/pwf/bill', $payload);

            // Decode respons dari Flip API
            $data = $response->json();
            // Jika respons tidak berhasil, lempar exception
            if (!$response->successful() || !isset($data['bill_payment'])) {
                throw new \Exception('Gagal membuat Virtual Account. Silakan coba lagi.');
            }

            // Ambil data dari respons
            $vaNo = $data['bill_payment']['receiver_bank_account']['account_number'];
            $vaChannel = $data['bill_payment']['receiver_bank_account']['account_type'];
            $vaBank = $data['bill_payment']['receiver_bank_account']['bank_code'];
            // $qris = $data['bill_payment']['receiver_bank_account']['qr_code_data'];
            $transactionalId = $data['link_id'];

            // Simpan transaksi ke database
            FlipTransaksiTabungan::create([
                'kode_pondok' => $kode_pondok,
                'noref' => $noref,
                'tanggal' => now($user->waktu_indonesia),
                'student_id' => $user->student_id,
                'nominal' => $nominal,
                'catatan' => $catatan,
                'status' => 'PENDING',
                'di_bayar' => 0,
                'va_no' => $vaNo,
                'va_nama' => null,
                'va_channel' => $vaChannel,
                'va_bank' => $vaBank,
                'transactionId' => $transactionalId,
                'Expired' => $data['expired_date'],
                'va_fee' => $fee,
                'create_at' => now($user->waktu_indonesia)
            ]);

            // Format pesan untuk WhatsApp
            $totalBayarFormatted = 'Rp. ' . number_format($nominal, 0, ',', '.');
            $tanggal = Carbon::now();

            // $whatsappService = new WhatsappServicePembayaran();
            // //dd($whatsappService);
            // $nowa = $user->student_parent_phone;
            // $pesan = <<<EOT
            // Bagian Administrasi  $nama_sekolah

            // Assalamualaikum warahmatullahi wabarakatuh,

            // Tanggal: {$tanggal}

            // Yth. Ayah/Bunda dari ananda {$user->student_full_name},

            // Terima kasih telah menggunakan aplikasi ePesantren. Berikut detail pembayaran dari top up tabungan yang perlu dilakukan:

            // ID transaksi    : {$transactionalId}
            // Total                 : {$totalBayarFormatted}
            // Nomor VA        : {$vaNo}
            // Bank                 : {$vaBank}

            // Mohon lakukan pembayaran sebelum:
            // {$data['expired_date']}

            // Pembayaran dapat dilakukan melalui aplikasi mobile atau metode lain yang tersedia.
            // Total pembayaran: {$totalBayarFormatted}

            // Hormat kami,
            // Bagian Administrasi
            // EOT;

            // // Kirim pesan WhatsApp

            // $whatsappService->kirimPesan($nowa, $pesan);

            // Siapkan data untuk respons sukses
            return [
                'is_correct' => 'success',
                'payment_url' => $data['payment_url'],
                'senderBankType' => $data['bill_payment']['sender_bank_type'],
                'va_number' => $vaNo,
                'bank' => $data['bill_payment']['sender_bank'],
                'label' => $vaChannel,
                'bayar_via' => $data['bill_payment']['sender_bank_type'],
                'expired' => $data['expired_date'],
                //'qris' => $qris
            ];
        } catch (\Exception $e) {
            // Tangani kesalahan dan kembalikan respons error
            return [
                'is_correct' => 'error',
                'message' => $e->getMessage(),
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
