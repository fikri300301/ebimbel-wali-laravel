<?php

namespace App\Http\Controllers;

use DateTime;
use Carbon\Carbon;
use App\Models\Kas;
use App\Models\Pos;
use App\Jobs\NotifWa;
use App\Models\Bebas;
use App\Models\Bulan;
use App\Models\major;
use App\Models\BniTrx;
use App\Models\Donasi;
use App\Models\LogTrx;
use App\Models\Period;
use App\Models\Account;
use App\Models\Banking;
use App\Models\InfoApp;
use App\Models\Payment;
use App\Models\Program;
use App\Models\Sekolah;
use App\Models\Setting;
use App\Models\Student;
use App\Models\BebasPay;
use App\Models\BniConfig;
use App\Models\StudentVa;
use App\Models\AkunIpayMu;
use App\Models\FlipDonasi;
use App\Models\JurnalUmum;
use App\Models\FlipChannel;
use App\Models\FlipCallback;
use Illuminate\Http\Request;
use App\Models\FlipTransaksi;
use App\Models\BebasPayMobile;
use App\Models\IpaymuTransaksi;
use App\Models\JurnalUmumDetail;
use App\Services\WhatsappService;
use App\Models\FlipCallbackDonasi;
use App\Services\DatabaseSwitcher;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\data_ipaymu_channel;
use Illuminate\Support\Facades\Log;
use App\Models\FlipCallbackTabungan;
use App\Services\WhatsappServiceOtp;
use Illuminate\Support\Facades\Http;
use App\Models\FlipTransaksiTabungan;
use App\Services\WhatsappServicePembayaran;
use Illuminate\Console\View\Components\Info;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;

class FlipPaymentController extends Controller
{
    protected $dbConfig;
    // protected $databaseSwitcher;

    // public function __construct(DatabaseSwitcher $databaseSwitcher)
    // {
    //     $this->databaseSwitcher = $databaseSwitcher;
    // }
    protected function getKodeSekolah()
    {
        try {
            $token = JWTAuth::parseToken();
            $claims = $token->getPayload();
            return $claims->get('kode_sekolah');
        } catch (TokenInvalidException $e) {
            return null;
        } catch (TokenExpiredException $e) {
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function setDbConfig()
    {
        try {
            $token = JWTAuth::parseToken();
            $claims = $token->getPayload();
            $this->dbConfig = $claims->get('db'); // Simpan ke variabel anggota
        } catch (TokenInvalidException $e) {
            $this->dbConfig = null;
        } catch (TokenExpiredException $e) {
            $this->dbConfig = null;
        } catch (\Exception $e) {
            $this->dbConfig = null;
        }
    }

    function RemoveSpecialChar($str)
    {
        $res = preg_replace('/[^a-zA-Z0-9_ -]/s', '', $str);
        return $res;
    }

    public function processPayment(Request $request)
    {

        $setting = Setting::where('setting_name', 'api_secret_key')->first();
        $currentConnection = DB::connection()->getDatabaseName();

        if ($currentConnection == 'adminsek_pelitaglobalmandiri') {
            // dd('anda menggunakan database pelita global');
            $student = auth()->user();
            $token = JWTAuth::parseToken();
            $claims = $token->getPayload();
            $kode_sekolah = $claims->get('kode_sekolah');
            // dd($kode_sekolah);
            $waktu = $claims->get('waktu_idonesia');
            //dd($waktu);
            $major = major::where('majors_id', $student->majors_majors_id)->first();
            $majorName = $major->majors_short_name;
            $validated = $request->validate([
                'id_transaksi' => 'required|string',
                // 'id_transaksi.*' => 'string',
            ]);

            BebasPayMobile::where('flip_no_trans', $request->id_transaksi)
                ->update([
                    'flip_status' => 'PENDING'
                ]);

            Bulan::where('flip_no_trans', $request->id_transaksi)
                ->update([

                    'flip_status' => null
                ]);


            $nameEmail = explode(" ", $student->student_full_name);
            $firstName = $nameEmail[0];
            $lastName = $nameEmail[1] ?? '';

            $namaemail = $this->RemoveSpecialChar($firstName) . $this->RemoveSpecialChar($lastName) . '@adminsekolah.net';
            $nominal = FlipTransaksi::where('id_transaksi', $request->id_transaksi)->first();
            // dd($nominal->nominal);
            $like = 'SP' . str_replace(" ", "", $majorName . $student->student_nis);
            $idMajors = $major;

            $noref =  time();
            $paymentNoref = $like . $noref;
            //  dd($paymentNoref);
            $noVa = StudentVa::where('student_nis', $student->student_nis)->first();
            //dd($noVa->no_va);
            $configBni = BniConfig::where('majors_id', $student->majors_majors_id)->where('is_active', 1)->first();
            //  dd($student->majors_majors_id);
            $secretKey = $configBni->secret_key;
            $clientId = $configBni->client_id;
            $url = $configBni->url;
            //  dd($secretKey, $clientId, $url);

            $data = [
                "secret_key" => $secretKey, // Secret key dari BNI
                "client_id" => $clientId, // Client ID dari Flip
                "customer_name" => $student->student_full_name,
                "customer_email" => $namaemail,
                "description" => 'Pembayaran atas nama ' . $student->student_full_name . '|' . $student->student_nis . ' |' . $kode_sekolah . '|' . $request->id_transaksi . '|' . 1 . '|' . $waktu,
                //  'description' => 'bayar',
                "datetime_expired" => now()->addDays(1)->format('Y-m-d H:i:s'), // Contoh: expired 1 hari dari sekarang
                "trx_amount" => $nominal->nominal,
                "trx_id" => $paymentNoref, // ID transaksi
                "type" => "createbilling",
                "virtual_account" => $noVa->no_va, // Nomor virtual account
                "billing_type" => 'c',
                "url" => $url,
            ];

            $dataBank = FlipChannel::where('bank', 'BNI')->first();
            $namaBank = $dataBank->bank;
            $metode = $dataBank->payment_channel;
            $bayar = $dataBank->cara_bayar;
            $logo = $dataBank->logo;

            //dd($namaBank);

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post('https://bni.indoweb.id/api/create_billing', $data);
            // dd($response->json());
            if ($response->json()['status'] == 'failed') {

                $configBni = BniConfig::where('majors_id', $student->majors_majors_id)->where('is_active', 1)->first();

                $secretKey = $configBni->secret_key;
                $clientId = $configBni->client_id;
                $url = $configBni->url;
                //    /dd('disini');
                $data = [
                    "secret_key" => $secretKey, // Secret key dari BNI
                    "client_id" =>  $clientId, // Client ID dari Flip
                    "customer_name" => $student->student_full_name,
                    "customer_email" => $namaemail,
                    "description" => 'Pembayaran atas nama ' . $student->student_full_name . '|' . $student->student_nis . ' |' . $kode_sekolah . '|' . $request->id_transaksi . '|' . 1 . '|' . $waktu,
                    //  'description' => 'bayar',
                    "datetime_expired" => now()->addDays(1)->format('Y-m-d H:i:s'), // Contoh: expired 1 hari dari sekarang
                    "trx_amount" => $nominal->nominal,
                    "trx_id" => $paymentNoref, // ID transaksi
                    "type" => "updatebilling",
                    "virtual_account" => $noVa->no_va, // Nomor virtual account
                    "billing_type" => 'c',
                    "url" => $url,
                ];

                //  dd($data);

                $dataBank = FlipChannel::where('bank', 'BNI')->first();
                $namaBank = $dataBank->bank;
                $metode = $dataBank->payment_channel;
                $bayar = $dataBank->cara_bayar;
                $logo = $dataBank->logo;

                // dd($namaBank);

                $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                ])->post('https://bni.indoweb.id/api/update_billing', $data);
                //  dd($response->json());
                // dd($waktu);
                $waktuAsli = Carbon::now('Asia/jakarta')->format('Y-m-d H:i:s');
                //  dd($waktuAsli);
                //dd($waktu);



                if ($waktu == 'WIT') {
                    $waktuAsli = Carbon::now('Asia/Makassar')->format('Y-m-d H:i:s');
                }
                if ($waktu == 'WITA') {
                    $waktuAsli = Carbon::now('Asia/Jayapura')->format('Y-m-d H:i:s');
                }
                if ($response->successful()) {
                    // Ubah response menjadi array
                    $responseData = $response->json();
                    //dd($responseData);
                    // Periksa apakah key 'data' dan 'virtual_account' ada di response
                    if (isset($responseData['data']['virtual_account'])) {
                        FlipTransaksi::where('id_transaksi',  $request->id_transaksi)
                            ->update([
                                'va_no' => $responseData['data']['virtual_account'],
                                'va_channel' => 'virtual_account',
                                'status' => 'PENDING',
                                'nominal' => $nominal->nominal,
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
                        $datetime_expired = $curdate->format('Y-m-d H:i:s');
                        // dd($datetime_expired);
                        //memasukkan ke bni trx
                        $BniTrx = [
                            'type' => 'updatebilling',
                            'noref' => $paymentNoref,
                            'trx_amount' => $nominal->nominal,
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
                            'payment_ntb' => 0,
                            'datetime_payment' => Carbon::now('Asia/jakarta')->format('Y-m-d H:i:s'),
                            'datetime_payment_iso8601' => Carbon::now('Asia/Jakarta')->format('Y-m-d H:i:s')
                        ];
                        $dataUpdate = BniTrx::where('student_id', $student->student_id)
                            ->where('trx_status', 'PENDING')
                            ->first();

                        $idTrx = $dataUpdate->id;
                        //  dd($idTrx);
                        //disini harusnya uodate
                        $updateBilling = BniTrx::findOrFail($idTrx);
                        //    dd($updateBilling);
                        $updateBilling->update([
                            'type' => 'updatebilling',
                        ]);
                        //  BniTrx::create($BniTrx);
                        // Jika ada, kembalikan custom response
                        // dd($responseData['data']['virtual_account']);
                        return response()->json([
                            'is_correct' => true,
                            'bayar_via' => 'virtual_account',
                            'bank' => $namaBank,
                            'label' => 'virtual_account',
                            'va' => $responseData['data']['virtual_account'], // Ambil dari response
                            'nominal' => (int)$nominal->nominal,
                            'total_bayar' => (int)$nominal->nominal,
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
                    }
                }
            }

            // dd($waktu);
            $waktuAsli = Carbon::now('Asia/jakarta')->format('Y-m-d H:i:s');
            //  dd($waktuAsli);
            //dd($waktu);



            if ($waktu == 'WIT') {
                $waktuAsli = Carbon::now('Asia/Makassar')->format('Y-m-d H:i:s');
            }
            if ($waktu == 'WITA') {
                $waktuAsli = Carbon::now('Asia/Jayapura')->format('Y-m-d H:i:s');
            }
            if ($response->successful()) {
                // Ubah response menjadi array
                $responseData = $response->json();

                // Periksa apakah key 'data' dan 'virtual_account' ada di response
                if (isset($responseData['data']['virtual_account'])) {
                    FlipTransaksi::where('id_transaksi',  $request->id_transaksi)
                        ->update([
                            'va_no' => $responseData['data']['virtual_account'],
                            'va_channel' => 'virtual_account',
                            'status' => 'PENDING',
                            'nominal' => $nominal->nominal,
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
                    $datetime_expired = $curdate->format('Y-m-d H:i:s');
                    //memasukkan ke bni trx
                    $BniTrx = [
                        'type' => 'createbilling',
                        'noref' => $paymentNoref,
                        'trx_amount' => $nominal->nominal,
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
                        'payment_ntb' => 0,
                        'datetime_payment' => Carbon::now('Asia/jakarta')->format('Y-m-d H:i:s'),
                        'datetime_payment_iso8601' => Carbon::now('Asia/Jakarta')->format('Y-m-d H:i:s')
                    ];

                    BniTrx::create($BniTrx);
                    //update juga noref nya flip_transaksi bulan dan
                    // Jika ada, kembalikan custom response
                    return response()->json([
                        'is_correct' => true,
                        'bayar_via' => 'virtual_account',
                        'bank' => $namaBank,
                        'label' => 'virtual_account',
                        'va' => $responseData['data']['virtual_account'], // Ambil dari response
                        'nominal' => (int)$nominal->nominal,
                        'total_bayar' => (int)$nominal->nominal,
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
                }
            } else {
                //  dd('coba');
                // Jika request ke API gagal
                $student = auth()->user();
                $token = JWTAuth::parseToken();
                $claims = $token->getPayload();
                $kode_sekolah = $claims->get('kode_sekolah');
                // dd($kode_sekolah);
                $waktu = $claims->get('waktu_idonesia');
                //dd($waktu);
                $major = major::where('majors_id', $student->majors_majors_id)->first();
                $majorName = $major->majors_short_name;
                $validated = $request->validate([
                    'id_transaksi' => 'required|string',
                    // 'id_transaksi.*' => 'string',
                ]);

                BebasPayMobile::where('flip_no_trans', $request->id_transaksi)
                    ->update([
                        'flip_status' => 'PENDING'
                    ]);

                Bulan::where('flip_no_trans', $request->id_transaksi)
                    ->update([

                        'flip_status' => null
                    ]);


                $nameEmail = explode(" ", $student->student_full_name);
                $firstName = $nameEmail[0];
                $lastName = $nameEmail[1] ?? '';

                $namaemail = $this->RemoveSpecialChar($firstName) . $this->RemoveSpecialChar($lastName) . '@adminsekolah.net';
                $nominal = FlipTransaksi::where('id_transaksi', $request->id_transaksi)->first();
                //   dd($nominal->nominal);
                $like = 'SP' . str_replace(" ", "", $majorName . $student->student_nis);
                $idMajors = $major;
                // $noref = Kas::getNoref($like, $idMajors);
                $noref =  time();
                $paymentNoref = $like . $noref;
                // dd($paymentNoref);
                $noVa = StudentVa::where('student_nis', $student->student_nis)->first();
                $configBni = BniConfig::where('majors_id', $student->majors_majors_id)->where('is_active', 1)->first();

                $secretKey = $configBni->secret_key;
                $clientId = $configBni->client_id;
                $url = $configBni->url;
                // dd($noVa->no_va);
                $data = [
                    "secret_key" =>  $secretKey, // Secret key dari BNI
                    "client_id" =>  $clientId, // Client ID dari Flip
                    "customer_name" => $student->student_full_name,
                    "customer_email" => $namaemail,
                    "description" => 'Pembayaran atas nama ' . $student->student_full_name . '|' . $student->student_nis . ' |' . $kode_sekolah . '|' . $request->id_transaksi . '|' . 1 . '|' . $waktu,
                    //  'description' => 'bayar',
                    "datetime_expired" => now()->addDays(1)->format('Y-m-d H:i:s'), // Contoh: expired 1 hari dari sekarang
                    "trx_amount" => $nominal->nominal,
                    "trx_id" => $paymentNoref, // ID transaksi
                    "type" => "updatetebilling",
                    "virtual_account" => $noVa->no_va, // Nomor virtual account
                    "billing_type" => 'c',
                    "url" => $url
                ];

                $dataBank = FlipChannel::where('bank', 'BNI')->first();
                $namaBank = $dataBank->bank;
                $metode = $dataBank->payment_channel;
                $bayar = $dataBank->cara_bayar;
                $logo = $dataBank->logo;

                // dd($namaBank);

                $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                ])->post('https://bni.indoweb.id/api/update_billing', $data);
                // dd($response->json());
                // dd($waktu);
                $waktuAsli = Carbon::now('Asia/jakarta')->format('Y-m-d H:i:s');
                //  dd($waktuAsli);
                //dd($waktu);



                if ($waktu == 'WIT') {
                    $waktuAsli = Carbon::now('Asia/Makassar')->format('Y-m-d H:i:s');
                }
                if ($waktu == 'WITA') {
                    $waktuAsli = Carbon::now('Asia/Jayapura')->format('Y-m-d H:i:s');
                }
                if ($response->successful()) {
                    // Ubah response menjadi array
                    $responseData = $response->json();

                    // Periksa apakah key 'data' dan 'virtual_account' ada di response
                    if (isset($responseData['data']['virtual_account'])) {
                        FlipTransaksi::where('id_transaksi',  $request->id_transaksi)
                            ->update([
                                'va_no' => $responseData['data']['virtual_account'],
                                'va_channel' => 'virtual_account',
                                'status' => 'PENDING',
                                'nominal' => $nominal->nominal,
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
                        $datetime_expired = $curdate->format('Y-m-d H:i:s');
                        //memasukkan ke bni trx
                        $BniTrx = [
                            'type' => 'updatebilling',
                            'noref' => $paymentNoref,
                            'trx_amount' => $nominal->nominal,
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
                            'payment_ntb' => 0,
                            'datetime_payment' => Carbon::now('Asia/jakarta')->format('Y-m-d H:i:s'),
                            'datetime_payment_iso8601' => Carbon::now('Asia/Jakarta')->format('Y-m-d H:i:s')
                        ];

                        //disini harusnya uodate
                        $dataUpdate = BniTrx::where('student_id', $student->student_id)
                            ->where('trx_status', 'PENDING')
                            ->first();

                        $idTrx = $dataUpdate->id;
                        $updateBilling = BniTrx::findOrFail($idTrx);
                        //   dd($updateBilling);
                        $updateBilling->update([
                            'type' => 'updatebilling',
                        ]);

                        // BniTrx::create($BniTrx);
                        // Jika ada, kembalikan custom response
                        return response()->json([
                            'is_correct' => true,
                            'bayar_via' => 'virtual_account',
                            'bank' => $namaBank,
                            'label' => 'virtual_account',
                            'va' => $responseData['data']['virtual_account'], // Ambil dari response
                            'nominal' => (int)$nominal->nominal,
                            'total_bayar' => (int)$nominal->nominal,
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
                    }
                } else {
                    // Jika request ke API gagal
                    return response()->json([
                        'is_correct' => false,
                        'message' => 'Gagal membuat Virtual Account. Silakan coba lagi.'
                    ], 430);
                }
            }
        }

        //  dd($setting);
        if ($setting) {
            try {
                $token = JWTAuth::parseToken();
                $claims = $token->getPayload();
                // dd($this->setDbConfig());
                $validated = $request->validate([
                    'id_transaksi' => 'required|string',
                    // 'id_transaksi.*' => 'string',
                ]);
                $PaymentGateway = $claims->get('payment');
                // dd($PaymentGateway);
                if ($PaymentGateway != 'AKTIF') {
                    return response()->json([
                        'message' => 'Anda tidak terdaftar dalam payment Gateway'
                    ], 409);
                }
                //dd($request->id_transaksi);
                //null kan flip_status dari semua pembayaran
                //  dd($request->id_transaksi);
                BebasPayMobile::where('flip_no_trans', $request->id_transaksi)
                    ->update([
                        'flip_status' => 'PENDING'
                    ]);

                Bulan::where('flip_no_trans', $request->id_transaksi)
                    ->update([

                        'flip_status' => 'PENDING'
                    ]);


                $token = JWTAuth::parseToken();
                $claims = $token->getPayload();
                $kode_sekolah = $claims->get('kode_sekolah');
                $nama_sekolah = $claims->get('schoolName');
                $waktu1 = $claims->get('waktu_indonesia');
                // $kode_sekolah = $this->getKodeSekolah();

                $user = auth()->user();
                $query = FlipTransaksi::where('student_id', $user->student_id);

                $idTransaksi = $request->input('id_transaksi');

                if ($idTransaksi) {
                    $query->where('id_transaksi', $idTransaksi);
                }
                $data = $query->first();
                // dd($data);

                if ($data) {
                    $nis = $user->student_nis;
                    $request->validate([
                        'payment_channel' => 'required|string',
                    ]);


                    // Menentukan fee

                    $nominal = $data->nominal;
                    $payment_channel = $request->input('payment_channel');
                    //  dd($payment_channel);
                    $caraBayar = FlipChannel::where('payment_channel', $payment_channel)->first();
                    // dd($caraBayar);
                    $fee = $caraBayar->fee;
                    //dd($fee);
                    $totalBayar = $nominal + $fee; // Nominal tanpa fee

                    // Memecah payment_channel untuk mendapatkan kode bank
                    $paymentChannelParts = explode('|', $payment_channel);
                    $paymentMethod = $paymentChannelParts[0];
                    //  dd($paymentMethod);
                    if (count($paymentChannelParts) !== 2) {
                        return response()->json([
                            'is_correct' => 'error',
                            'message' => 'Invalid payment channel format.',
                        ], 400);
                    }

                    $nameEmail = explode(" ", $user->student_full_name);
                    $firstName = $nameEmail[0];
                    $lastName = $nameEmail[1] ?? '';

                    $email = $this->RemoveSpecialChar($firstName) . $this->RemoveSpecialChar($lastName) . '@adminsekolah.net';
                    // dd($email);

                    // Menyimpan kode bank (misal BRI)
                    $senderBank = strtolower($paymentChannelParts[1]);
                    $kodeBayar = 1;
                    // dd($waktu1);
                    // Menyusun payload untuk Flip API
                    $payload = [
                        'title' => 'Pembayaran atas nama ' .  $user->student_full_name . '|' . $nis . ' | ' . $kode_sekolah . ' | ' . $idTransaksi . '|' . $kodeBayar . '|' . $waktu1,
                        'amount' => $nominal + $fee,
                        'total_bayar' => $totalBayar,
                        'fee' => $fee,
                        'type' => 'SINGLE',
                        'expired_date' => now('WIB')->addHours(6)->format('Y-m-d H:i'),
                        //'redirect_url' => "https://mobile.epesantren.co.id/walsan/callback_semua.php",
                        // 'redirect_url' => "https://0a4f-182-253-54-224.ngrok-free.app/api/flip/callback",
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
                    //  dd($payload);


                    // Mendapatkan konfigurasi Flip API (Test atau Live)
                    $flipConfig = $this->getFlipConfig();

                    // Mengirim permintaan ke Flip API
                    $response = $this->createPaymentToFlip($payload, $flipConfig, $idTransaksi);
                    // dd($response['va_number']);
                    // if ($response['va_number'] == null) {
                    //     return response()->json([
                    //         'is_correct' => false,
                    //         'message' => 'Va tidak tergenerate'
                    //     ], 430);
                    // }

                    if ($response['is_correct'] == 'error') {
                        throw new \Exception("Gagal membuat Virtual Account. Silakan coba lagi.");
                    }


                    //dd($caraBayar);
                    //update data pembayaran
                    // dd($idTransaksi);
                    $waktu = $user->waktu_indonesia;
                    $waktuAsli = Carbon::now('Asia/jakarta')->format('Y-m-d H:i:s');
                    //  dd($waktuAsli);
                    //dd($waktu);

                    if ($waktu == 'WIT') {
                        $waktuAsli = Carbon::now('Asia/Makassar')->format('Y-m-d H:i:s');
                    }
                    if ($waktu == 'WITA') {
                        $waktuAsli = Carbon::now('Asia/Jayapura')->format('Y-m-d H:i:s');
                    }
                    $update = FlipTransaksi::where('id_transaksi', $idTransaksi)->first();

                    if ($update) {
                        //   dd($waktuAsli);
                        // Perbarui data FlipCallback
                        FlipTransaksi::where('id_transaksi', $idTransaksi)
                            ->update([
                                'va_no' => $response['va_number'] ?? null,
                                'va_channel' => $response['bayar_via'] ?? null,
                                'status' => 'PENDING',
                                'nominal' => $totalBayar,
                                'tanggal' => $waktuAsli,
                                'va_fee' => $fee,
                                'expired' => $response['expired'],
                                'va_bank' => $response['bank'] ?? null,
                                'transactionId' => $update->transactionId ?? null,
                            ]);
                    }
                    $totalBayarFormatted = 'Rp. ' . number_format($totalBayar, 0, ',', '.');
                    $tanggal = Carbon::now();
                    //             $pesan = <<<EOT
                    // Bagian Administrasi  $nama_sekolah

                    // Assalamualaikum warahmatullahi wabarakatuh,

                    // Tanggal: {$tanggal}

                    // Yth. Ayah/Bunda dari ananda {$user->student_full_name},

                    // Terima kasih telah menggunakan aplikasi ePesantren. Berikut detail pembayaran yang perlu dilakukan:

                    // ID transaksi    : {$idTransaksi}
                    // Total                 : {$totalBayarFormatted}
                    // Nomor VA        : {$response['va_number']}
                    // Bank                 : {$response['bank']}

                    // Mohon lakukan pembayaran sebelum:
                    // {$response['expired']}

                    // Pembayaran dapat dilakukan melalui aplikasi mobile atau metode lain yang tersedia.
                    // Total pembayaran: {$totalBayarFormatted}

                    // Hormat kami,
                    // Bagian Administrasi
                    // EOT;
                    //             // NotifWa::dispatchAfterResponse($user->student_parent_phone, $pesan);
                    //             //dd($user->student_parent_phone);
                    //             //notif wa pembayaran
                    //             // dd($update);
                    //             //dd($caraBayar->cara_bayar)
                    //             $whatsappService = new WhatsappServicePembayaran();
                    //             $nowa = $user->student_parent_phone;
                    //             $whatsappService->kirimPesan($nowa, $pesan);

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
                }
            } catch (\Throwable $th) {
                return response()->json([
                    'is_correct' => false,
                    'message' => $th->getMessage(),
                    'line' => $th->getLine(),
                ], 500);
            }
        } else {
            // dd('setting tidak ada');
            //development
            // $secret = 'SANDBOX5842A926-580F-4C08-9D84-B576859A95B4';
            // $va = '0000001233640003';

            //production
            $getsecret = Setting::where('setting_name', 'key_ipaymu')->first();
            $secret = $getsecret->setting_value;
            $getVa = Setting::where('setting_name', 'va_ipaymu')->first();
            $va = $getVa->setting_value;

            //url development
            // $url = 'https://sandbox.ipaymu.com/api/v2/payment/direct';
            // //$notifyUrl = 'https://mobile.epesantren.co.id/walsan/notif_ipaymuall.php';
            // $notifyUrl = 'https://api-epesantren-wali.ninetale.my.id/api/ipaymu/callback';

            //url production
            $url = 'https://my.ipaymu.com/api/v2/payment/direct';
            $notifyUrl = 'https://api.adminsekolah.net/api/ipaymu/callback'; //ini nanti masih diganti

            $token = JWTAuth::parseToken();
            $claims = $token->getPayload();
            $kode_sekolah = $claims->get('kode_sekolah');
            $waktu1 = $claims->get('waktu_indonesia');
            // dd($this->setDbConfig());
            $validated = $request->validate([
                'id_transaksi' => 'required|string',
                'payment_channel' => 'required',
            ]);
            $PaymentGateway = $claims->get('payment');
            // dd($PaymentGateway);
            if ($PaymentGateway != 'AKTIF') {
                return response()->json([
                    'message' => 'Anda tidak terdaftar dalam payment Gateway'
                ], 409);
            }

            BebasPayMobile::where('ipaymu_no_trans', $request->id_transaksi)
                ->where('ipaymu_status', 'READY')
                ->update([
                    'ipaymu_status' => 'PENDING'
                ]);

            Bulan::where('ipaymu_no_trans', $request->id_transaksi)
                ->update([
                    //  'ipaymu_no_trans' => null, //ini tambahan
                    'ipaymu_status' => null
                ]);
            $user = auth()->user();
            $student_full_name = $user->student_full_name;
            $newStr = explode(" ", $user->student_full_name);
            //  $secret = '39F0ADF6-7E9D-4AEB-9934-53DB0145844E';
            $firstname = $newStr[0];
            $lastname = $newStr[1];
            $email =  $this->RemoveSpecialChar($firstname) . $this->RemoveSpecialChar($lastname) . '@epesantren.co.id';

            $phone = $user->student_parent_phone;
            if ($phone == NULL) {
                $kode = '08';
                $rand = rand(1000000000, 9999999999);
                $phone = $kode . $rand;
            } else {
                $phone = str_replace('+62', '0', $phone);
            }
            //  dd($phone);
            $amount = IpaymuTransaksi::where('id_transaksi', $request->id_transaksi)->first();
            // dd($amount);
            $payment_channel = data_ipaymu_channel::where('payment_channel', $request->payment_channel)->first();
            $payment = explode("|", $payment_channel->payment_channel);
            $bank_fee = $payment_channel->fee;

            //dd($payment);
            $method       = 'POST';
            $paymentMethod = $payment[0];
            $paymentChannel = $payment[1];
            $kodePembayaran = 1;
            // dd($paymentMethod);
            // dd($phone);
            $body['name']    = $student_full_name;
            $body['email']   = $email;
            $body['phone']   = $phone;
            $body['amount']  = $amount->nominal + $bank_fee;
            $body['notifyUrl']   = $notifyUrl;
            $body['expired']   = '6';
            $body['expiredType']   = 'hours';
            $body['comments']   = 'ePesantren';
            $body['referenceId']   = $kode_sekolah . '|' . $user->student_nis . '|' . $kodePembayaran;
            $body['paymentMethod']  = $paymentMethod;
            $body['paymentChannel']   = $paymentChannel;
            $body['description']   = 'ePesantren';

            //dd($body['phone']);

            $jsonBody     = json_encode($body, JSON_UNESCAPED_SLASHES);
            // dd($jsonBody);
            // echo '<br>'. $jsonBody;
            $requestBody  = strtolower(hash('sha256', $jsonBody));
            $stringToSign = strtoupper($method) . ':' . $va . ':' . $requestBody . ':' . $secret;
            // dd($stringToSign);
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

                // Customize the response
                $customResponse = [
                    'is_correct' => true,
                    'bayar_via' => $responseData['Data']['Via'],
                    'bank' =>  $responseData['Data']['Channel'],
                    'label' => $responseData['Data']['Via'],
                    'va' => $responseData['Data']['PaymentNo'],
                    'nominal' => $responseData['Data']['SubTotal'],
                    'total_bayar' => $amount->nominal + $bank_fee,
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
                $waktu = $user->waktu_indonesia;
                $waktuAsli = Carbon::now('Asia/jakarta')->format('Y-m-d H:i:s');
                //  dd($waktuAsli);
                //dd($waktu);

                if ($waktu == 'WIT') {
                    $waktuAsli = Carbon::now('Asia/Makassar')->format('Y-m-d H:i:s');
                }
                if ($waktu == 'WITA') {
                    $waktuAsli = Carbon::now('Asia/Jayapura')->format('Y-m-d H:i:s');
                }
                // dd($waktuAsli);
                //update ipaymu transaksi
                $update = IpaymuTransaksi::where('id_transaksi', $request->id_transaksi)->first();
                if ($update) {
                    IpaymuTransaksi::where('id_transaksi', $request->id_transaksi)
                        ->update([
                            'va_no' =>  $responseData['Data']['PaymentNo'],
                            'va_nama' => $responseData['Data']['PaymentName'],
                            'va_channel' =>  $payment_channel->payment_channel,
                            'status' => 'PENDING',
                            'nominal' => $amount->nominal + $bank_fee,
                            'tanggal' => $waktuAsli,
                            'va_fee' => $bank_fee,
                            'expired' => $responseData['Data']['Expired'],
                            'va_bank' =>  $responseData['Data']['Channel'],
                            'va_transactionId' => $responseData['Data']['TransactionId']
                        ]);
                };

                return response()->json($customResponse);
            } catch (\Exception $e) {
                return response()->json([
                    'message' => 'An error occurred',
                    'error' => $e->getMessage()
                ], 500);
            }
        }
    }

    /**
     * Get Flip API configuration for test/live environment.
     */
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
        //dd($flipMode);
        // Mengambil API key dari tabel setting berdasarkan mode
        if ($flipMode === 'live') {
            $apiKey = Setting::where('setting_name', 'api_secret_key')->first();
            $getApiKey = $apiKey->setting_value;
            $url = 'https://bigflip.id/api/v2';
        } else {
            $apiKey = Setting::where('setting_name', 'api_secret_key_test')->first();
            $getApiKey = $apiKey->setting_value;
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

    /**
     * Create the payment request to Flip API.
     */
    private function createPaymentToFlip($payload, $flipConfig, $idTransaksi)
    {
        try {
            $response = Http::withBasicAuth($flipConfig['secret_key'], '')
                ->asForm()
                ->post($flipConfig['url'] . '/pwf/bill', $payload);

            // Decode respons dari Flip API
            $data = $response->json();
            if (!$response->successful() || !isset($data['bill_payment'])) {
                throw new \Exception('Gagal membuat Virtual Account. Silakan coba lagi.');
            }

            //update $transactionalId
            $transactionalId = $data['link_id'];
            if ($transactionalId) {
                FlipTransaksi::where('id_transaksi', $idTransaksi)->update(['transactionId' => $transactionalId]);
            }
            //dd($transactionalId);

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
        // Mengirim permintaan POST ke Flip API

    }
    private function getIpaymuConfig()
    {
        $settings = Setting::whereIn('setting_name', [
            'va_ipaymu',
            'key_ipaymu',
            'mode_ipaymu'
        ])->pluck('setting_value', 'setting_name');

        return [
            'va' => $settings['va_ipaymu'] ?? config('ipaymu.va'),
            'api_key' => $settings['key_ipaymu'] ?? config('ipaymu.api_key'),
            'url' => $settings['mode_ipaymu'] === 'sandbox' ?
                'https://sandbox.ipaymu.com/api/v2/payment' :
                'https://my.ipaymu.com/api/v2/payment'
        ];
    }


    public function paymentCallback(Request $request)
    {
        // Ambil data dan token dari request POST yang dikirim Flip
        //  error_log($request);
        $data = $request->input('data') ?: null;
        $token = $request->input('token') ?: null;

        $decoded_data = json_decode($data, true);
        $data1 = $decoded_data['bill_title'];
        $parts = explode('|', $data1);
        $kodePesantren = trim($parts[2]);
        $sekolah = Sekolah::where('kode_sekolah', $kodePesantren)->first();

        if (!$sekolah) {
            return response()->json(['error' => 'Sekolah tidak ditemukan.'], 404);
        }
        $sekolahModel = new Sekolah();
        $sekolahModel->setDatabaseName($sekolah->db);
        // $this->databaseSwitcher->switchDatabase($sekolah->db, $sekolahModel); //tambhakan ini

        $waktusekolah = $sekolah->waktu_indonesia;
        // dd($sekolahModel);
        $sekolahModel->switchDatabase();
        $tokenDb = Setting::where('setting_name', 'token_validasi_test')->first(); //tokeb validasi test
        //$tokenDb = Setting::where('setting_name', 'token_validasi')->first(); //token validasi live

        // Verifikasi token (cocokkan dengan token yang diterima dari Flip Dashboard)
        // if ($token === $tokenDb->setting_value)
        //if ($token === '$2y$13$airzQQ4ocKvjKB3zJyI/2.hOiiZywNLNigiBJlYP4Jq5ZhTFgBWPC') {
        if ($token === $tokenDb->setting_value) {
            // Token valid, decode data JSON
            $decoded_data = json_decode($data, true);

            //dd($db);

            // Cek apakah decoding berhasil
            if (json_last_error() === JSON_ERROR_NONE) {
                $status = $decoded_data['status'];
                $data1 = $decoded_data['bill_title'];
                $userId = $decoded_data["user_id"] ?? null;

                $parts = explode('|', $data1);
                $kodePesantren = trim($parts[2]);
                $kodePembayaran = trim($parts[4]);
                //  1 $waktu1 = trim($parts[5]);
                //notif wa donasi
                $kodeDonasi = trim($parts[3]);

                $nis = trim($parts[1]);
                // dd($kodePesantren);
                if ($status == "SUCCESSFUL") {
                    if ($kodePembayaran == 1) {
                        $sekolah = Sekolah::where('kode_sekolah', $kodePesantren)->first();

                        if (!$sekolah) {
                            return response()->json(['error' => 'Sekolah tidak ditemukan.'], 404);
                        }
                        $sekolahModel = new Sekolah();
                        $sekolahModel->setDatabaseName($sekolah->db);
                        // dd($sekolahModel);
                        $sekolahModel->switchDatabase();

                        $dataCallback = [
                            'trx_id' => $decoded_data['id'],
                            'bill_id' => $decoded_data['bill_link_id'],
                            'bill_link' => $decoded_data['bill_link'],
                            'bill_title' => $decoded_data['bill_title'],
                            'sender_bank_type' => $decoded_data['sender_bank_type'],
                            'sender_bank' => $decoded_data['sender_bank'],
                            'amount' => $decoded_data['amount'],
                            'status' => $decoded_data['status'],
                            'sender_name' => $decoded_data['sender_name'],
                            'sender_email' => $decoded_data['sender_email'],
                        ];
                        $bill_id = $decoded_data['bill_link_id'];
                        FlipCallback::create($dataCallback);
                        $bill_id = $decoded_data['bill_link_id'];
                        $responStatus = FlipCallback::where('bill_id', $bill_id)->first();
                        $status = $responStatus->status;
                        $update = FlipTransaksi::where('transactionId', $bill_id)->first();
                        $student = Student::where('student_nis', $nis)->first();
                        $major = $student->majors_majors_id;
                        $majorId = major::where('majors_id', $major)->first();
                        $majorName = $majorId->majors_short_name;
                        $like = 'SP' . str_replace(" ", "", $majorName . $nis);
                        $idMajors = $major;
                        $noref = Kas::getNoref($like, $idMajors);
                        $paymentNoref = $like . $noref;

                        $noTrans = $update->id_transaksi;

                        //update status pembayaran fi flip_transaksi berdasarkan $status
                        if ($update && $paymentNoref) {
                            // Update status pembayaran pada flip_transaksi
                            $update->noref = $paymentNoref;
                            $update->status = $status;
                            $update->save();
                        } else {
                            // Handle jika transaksi tidak ditemukan
                            // Misalnya, bisa log atau memberikan respon error
                            Log::error("Transaksi dengan ID $bill_id tidak ditemukan.");
                        }

                        $amout = $decoded_data['amount'];
                        $bankSender = strtoupper($decoded_data['sender_bank']);
                        $bankVee = FlipChannel::where('kode', $bankSender)->first();
                        $fee = $bankVee->fee;
                        $totalBayarTanpaFee = $amout - $fee;

                        //yang di update adalah bulan noref, bulan account id ,bulan_status, bulan_date_pay,user_id,flip_status
                        // $waktu = $student->waktu_indonesia;
                        $waktu = $waktusekolah;
                        //update table bulan
                        $bulan = Bulan::where('flip_no_trans', $update->id_transaksi)->get();
                        $statusFlip = 'LUNAS';
                        $statusBulan = 1;
                        $userId = $student->student_id;
                        $major = $student->majors_majors_id;
                        $akunIpaymu = AkunIpayMu::where('unit_id', $major)->where('tipe', 'pembayaran')->first();
                        $akunId = $akunIpaymu->akun_id;
                        $idTransaksiPesan = $update->id_transaksi;
                        //ambil user id terus ke major id ke table akun ipaymu ambil unit_id == majors ambil akun_id dari tabel account

                        if ($bulan->count() > 0) {
                            foreach ($bulan as $value) {
                                $value->update([
                                    'bulan_account_id' => $akunId,
                                    'flip_status' => $statusFlip,
                                    'bulan_noref' => $paymentNoref,
                                    'bulan_status' => $statusBulan,
                                    'bulan_date_pay' => now($waktu),
                                    'user_user_id' => $userId,
                                ]);
                            }
                        }

                        //update table bebas_pay_mobile
                        $bebasPayMobile = BebasPayMobile::where('flip_no_trans', $noTrans)->get();
                        $BebasNoRef = $paymentNoref;
                        // $bebasPayAccountId = $akunId;

                        foreach ($bebasPayMobile as $value) {
                            $value->update([
                                'bebas_pay_noref' => $BebasNoRef,
                                'bebas_pay_account_id' => $akunId,
                                'bebas_pay_last_update' => now($waktu),
                                'flip_status' => 'LUNAS'
                            ]);
                        }

                        //insert ke table bebas_pay berdasarkan id_transaksi
                        $bebasPay = BebasPayMobile::where('flip_no_trans', $noTrans)->get();
                        // dd($bebasPay);
                        if ($bebasPay->isNotEmpty()) {
                            $insertBebasPay = [];
                            foreach ($bebasPay as $value) {
                                $insertBebasPay[] = [
                                    'bebas_bebas_id' => $value->bebas_bebas_id,
                                    'bebas_pay_noref' => $value->bebas_pay_noref,
                                    'bebas_pay_account_id' => $value->bebas_pay_account_id,
                                    'bebas_pay_number' => $value->bebas_pay_number,
                                    'bebas_pay_bill' => $value->bebas_pay_bill,
                                    'bebas_pay_desc' => $value->bebas_pay_desc,
                                    'user_user_id' => $value->user_user_id,
                                    'sekolah_id' => $value->sekolah_id,
                                    'bebas_pay_input_date' => now($waktu),
                                    'flip_no_trans' => $value->flip_no_trans,
                                    'flip_status' => $value->flip_status,
                                    'bebas_pay_last_update' => $value->bebas_pay_last_update,
                                ];
                            }
                            BebasPay::insert($insertBebasPay);
                        }

                        //ambil nilai bebas_bebas_id dan bebas_pay_bill dari table bebas_pay_mobile
                        $bebasPayId = $bebasPay->pluck('bebas_bebas_id')->toArray();
                        $bebasPayBill = $bebasPay->pluck('bebas_pay_bill')->toArray();

                        //setelah di ambil cocokan dengan table bebas cocokan bebas id nya lalu jumlahkan total angka yang sudah ada di field bebas_total_pay
                        foreach ($bebasPayId as $index => $id) {
                            // Cari record di tabel bebas berdasarkan bebas_bebas_id
                            $bebasRecord = Bebas::where('bebas_id', $id)->first();

                            if ($bebasRecord) {
                                // Update bebas_total_pay dengan menambahkan nilai bebas_pay_bill
                                $bebasRecord->bebas_total_pay += $bebasPayBill[$index]; // Pastikan index sesuai
                                $bebasRecord->save(); // Simpan perubahan
                            }
                        }

                        $tanggal = Carbon::now($waktu);
                        // $totalBayarFormatted = 'Rp. ' . number_format($totalBayarTanpaFee, 0, ',', '.');
                        $totalBayarFormatted = 'Rp. ' . number_format($amout, 0, ',', '.');
                        $va = FlipTransaksi::where('id_transaksi', $noTrans)->first();
                        $pesan = <<<EOT
                        Bagian Administrasi  {$sekolah->nama_sekolah}

                        Assalamualaikum warahmatullahi wabarakatuh,

                        Tanggal: {$tanggal}

                        Yth. Ayah/Bunda dari ananda {$student->student_full_name},

                        Terima kasih telah menggunakan aplikasi ePesantren. Berikut detail pembayaran yang gagal:

                        ID transaksi    : {$noTrans}
                        Total                 : {$totalBayarFormatted}
                        Nomor VA        : {$va->va_no}
                        Bank                 : {$va->va_bank}



                        Terimakah telah melakukan pembayaran.

                        Hormat kami,
                        Bagian Administrasi
                        EOT;

                        // notif wa pembayaran
                        $whatsappService = new WhatsappServicePembayaran();
                        $nowa = $student->student_parent_phone;
                        $whatsappService->kirimPesan($nowa, $pesan);

                        //function mendapatkan nominal tanpa fee
                        //flip transaksi model pemicu nya transactionalId

                        //bulan_id LogTrx
                        $bulanLogTrx = Bulan::where('flip_no_trans', $noTrans)->get();
                        $bebasLogTrx = BebasPay::where('flip_no_trans', $noTrans)->get();


                        //log_trx bulan yang di isi bulan_bulan_id
                        $logTrxData = [];

                        // Proses data dari bulanLogTrx
                        foreach ($bulanLogTrx as $bulan) {
                            $logTrxData[] = [
                                'student_student_id' => $student->student_id,
                                'bulan_bulan_id' => $bulan->bulan_id,
                                'bebas_pay_bebas_pay_id' => null, // Tidak diisi
                                'sekolah_id' => $kodePesantren,
                                'log_trx_input_date' => now($waktu),
                                'log_trx_last_update' => now($waktu),
                            ];
                        }

                        // Proses data dari bebasLogTrx
                        foreach ($bebasLogTrx as $bebas) {
                            $logTrxData[] = [
                                'student_student_id' => $student->student_id,
                                'bulan_bulan_id' => null, // Tidak diisi
                                'bebas_pay_bebas_pay_id' => $bebas->bebas_bebas_id,
                                'sekolah_id' => $kodePesantren,
                                'log_trx_input_date' => now($waktu),
                                'log_trx_last_update' => now($waktu),
                            ];
                        }

                        // Masukkan semua data dalam satu proses insert
                        LogTrx::insert($logTrxData);


                        //insert info app
                        $infoApp = [
                            'student_id' => $student->student_id,
                            'info' => 'Pembayaran atas nama ' . $student->student_full_name . ' dengan nis ' . $student->student_nis . ' sebesar ' . $totalBayarFormatted . ' berhasil dilakukan.',
                            'created_at' => now($waktu),
                        ];
                        //insert ke table infoApp
                        InfoApp::insert($infoApp);
                        //bebas_pay_mobile
                        //bebas_pay
                        //bebas
                        //log_trx bebas yang di isi bebas_bebas_pay_id

                        //insert kas
                        $period = Period::where('period_status', 1)->first();
                        $kasMonthId = Carbon::now()->month;
                        $dataKas = [
                            'kas_noref' => $paymentNoref,
                            'kas_period' => $period->period_id,
                            'kas_date' => now($waktu),
                            'kas_month_id' => $kasMonthId,
                            'kas_account_id' => $akunId,
                            'kas_majors_id' => $student->majors_majors_id,
                            'kas_note' => 'Pembayaran pesantren ' . $student->student_full_name,
                            'kas_category' => 1,
                            'kas_receipt' => ' ',
                            'kas_tax_receipt' => ' ',
                            'kas_kredit' => 0,
                            'kas_debit' => $totalBayarTanpaFee,
                            'kas_user_id' => $student->student_id,
                            'kas_input_date' => now($waktu),
                            'kas_last_update' => now($waktu)
                        ];

                        Kas::insert($dataKas);

                        //insert jurnal umum
                        $dataJurnalUmum = [
                            //sekolah_id di isi dari major
                            'sekolah_id' => $student->majors_majors_id,
                            'keterangan' => 'pembayaran santri' . $student->student_full_name,
                            'noref' => $paymentNoref,
                            'tanggal' => now($waktu),
                            'pencatatan' => 'auto',
                            'waktu_update' => now($waktu),
                            'keterangan_lainnya' => ' '
                        ];

                        $idJurnal = JurnalUmum::insertGetId($dataJurnalUmum);

                        $cekBulan = Bulan::where('bulan_noref', $paymentNoref)->get();
                        $cekBebas = BebasPay::where('bebas_pay_noref', $paymentNoref)->get();

                        $accountId = Bulan::where('bulan_noref', $paymentNoref)->first();
                        $accountCode = $accountId ? Account::where('account_id', $accountId->bulan_account_id)->first() : null;

                        $bebasId1 = BebasPay::where('bebas_pay_noref', $paymentNoref)->first();
                        $bebasAccountCode = $bebasId1 ? Account::where('account_id', $bebasId1->bebas_pay_account_id)->first() : null;


                        if (($accountCode && $accountCode->account_code) || ($bebasAccountCode && $bebasAccountCode->account_code)) {
                            $JurnalUmumDetailDebit = [
                                'id_jurnal' => $idJurnal,
                                'account_code' => $accountCode->account_code ?? $bebasAccountCode->account_code ?? null,
                                'debet' => $totalBayarTanpaFee,
                                'kredit' => 0.00
                            ];
                            JurnalUmumDetail::insert($JurnalUmumDetailDebit);
                        }

                        if ($cekBulan->isNotEmpty()) {
                            $accountId = Bulan::where('bulan_noref', $paymentNoref)->first();
                            $accountCode = Account::where('account_id', $accountId->bulan_account_id)->first();

                            $accountCodeKredit = Bulan::where('bulan_noref', $paymentNoref)->get();
                            $paymentIds = $accountCodeKredit->pluck('payment_payment_id');
                            $bulanIds = $accountCodeKredit->pluck('bulan_id');
                            $payment = Payment::whereIn('payment_id', $paymentIds)->get();

                            //     // Loop untuk mengambil 'pos_pos_id' dari setiap elemen dalam array
                            $posPosIds = $payment->map(function ($paymentItem) {
                                return $paymentItem->pos_pos_id;
                            });

                            //     // nilai pos
                            $accountId1 = Pos::whereIn('pos_id', $posPosIds)->get();
                            $accountIds = $accountId1->map(function ($item) {
                                return $item->account_account_id;
                            });

                            //     //mencari nilai account code
                            $accountCodes = Account::whereIn('account_id', $accountIds)->get();
                            $getAccountCodes = $accountCodes->map(function ($item) {
                                return $item->account_code;
                            });

                            //     //mecari nilai pembayaran di dapat dari bulan id
                            $payKredit = Bulan::whereIn('bulan_id', $bulanIds)->get();
                            $getPayKredit = $payKredit->map(function ($item) {
                                return $item->bulan_bill;
                            });

                            $jurnalUmumDetailKreditbulan = $getAccountCodes->map(function ($accountCode, $index) use ($idJurnal, $getPayKredit) {
                                return [
                                    'id_jurnal' => $idJurnal,
                                    'account_code' => $accountCode,
                                    'debet' => 0.00,
                                    'kredit' => $getPayKredit[$index],
                                ];
                            });

                            JurnalUmumDetail::insert($jurnalUmumDetailKreditbulan->toArray());
                        }

                        if ($cekBebas->isNotEmpty()) {
                            // Mencari bebas account_id (hasilnya array integer)
                            $bebasPayAccountIds = BebasPay::where('bebas_pay_noref', $paymentNoref)->pluck('bebas_pay_id')->toArray();

                            //mencari nilai bebas
                            $bebasId = BebasPay::whereIn('bebas_pay_id', $bebasPayAccountIds)->pluck('bebas_bebas_id')->toArray();


                            //mencari bebas payment_id
                            $payment = Bebas::whereIn('bebas_id', $bebasId)->pluck('payment_payment_id')->toArray();


                            //mencari pos id
                            $pos = Payment::whereIn('payment_id', $payment)->pluck('pos_pos_id')->toArray();


                            //mancari account id
                            $account = Pos::whereIn('pos_id', $pos)->pluck('account_account_id')->toArray();


                            //mencari account code
                            $accountCodes = Account::whereIn('account_id', $account)->pluck('account_code')->toArray();


                            // // Mendapatkan debet
                            $bebasBills = BebasPay::whereIn('bebas_pay_id', $bebasPayAccountIds)->get();
                            $getBebasBill = $bebasBills->pluck('bebas_pay_bill', 'bebas_pay_id')->toArray();


                            $jurnalUmumDetailBebas = [];
                            foreach ($accountCodes as $index => $accountCode) {
                                $jurnalUmumDetailBebas[] = [
                                    'id_jurnal' => $idJurnal,
                                    'account_code' => $accountCode,
                                    'debet' => 0.00,
                                    'kredit' => $getBebasBill[$bebasPayAccountIds[$index]] ?? 0.00, // Cek data terkait
                                ];
                            }

                            // Insert ke tabel JurnalUmumDetail
                            JurnalUmumDetail::insert($jurnalUmumDetailBebas);
                        }

                        return response()->json([
                            'is_correct' => 'success',
                            'message' => 'Callback data decoded successfully.',
                            'data' => $decoded_data,
                            'kode_pesantren' => $kodePesantren,
                            'nis' => $nis,
                            //'noTrans' => $noTrans,
                            'status' => $status,
                            // 'noref' => $paymentNoref,
                            // 'bebas' => $bebasPayAccountIds,
                            // 'bebas_id' => $bebasId,
                            // 'payment_id' =>  $payment,
                            // 'pos_id' => $pos,
                            // 'account' => $account,
                            // 'account_code' => $accountCode,
                            // 'bill_payment' => $getBebasBill,
                            // 'bebas_bill' => $getBebasBill
                            //'bebas_pay_id' => $getBebasPayId,
                            // 'payment_ids' =>  $posPosIds,
                            // 'accound_ids' => $accountIds,
                            // 'accountCode' => $getAccountCodes,
                            // 'bulan_ids' => $bulanIds

                        ], 200);
                    } elseif ($kodePembayaran == 2) {

                        $sekolah = Sekolah::where('kode_sekolah', $kodePesantren)->first();

                        if (!$sekolah) {
                            return response()->json(['error' => 'Sekolah tidak ditemukan.'], 404);
                        }
                        $sekolahModel = new Sekolah();
                        $sekolahModel->setDatabaseName($sekolah->db);
                        // dd($sekolahModel);
                        $sekolahModel->switchDatabase();

                        $dataCallbackTabungan = [
                            'trx_id' => $decoded_data['id'],
                            'bill_id' => $decoded_data['bill_link_id'],
                            'bill_link' => $decoded_data['bill_link'],
                            'bill_title' => $decoded_data['bill_title'],
                            'sender_bank_type' => $decoded_data['sender_bank_type'],
                            'sender_bank' => $decoded_data['sender_bank'],
                            'amount' => $decoded_data['amount'],
                            'status' => $decoded_data['status'],
                            'sender_name' => $decoded_data['sender_name'],
                            'sender_email' => $decoded_data['sender_email'],
                        ];

                        FlipCallbackTabungan::create($dataCallbackTabungan);

                        $bill_id = $decoded_data['bill_link_id'];
                        $responStatus = FlipCallbackTabungan::where('bill_id', $bill_id)->first();
                        $status = $responStatus->status;
                        $update = FlipTransaksiTabungan::where('transactionId', $bill_id)->first();
                        $student = Student::where('student_nis', $nis)->first();
                        $waktu = $student->waktu_indonesia;

                        if ($update) {
                            $update->status = $status;
                            $update->di_bayar = $decoded_data['amount'];

                            $update->save();
                        }

                        $period = Period::where('period_status', 1)->first();
                        $data1 = $decoded_data['bill_title'];

                        $parts = explode('|', $data1);
                        $note = trim($parts[3]);


                        //data banking
                        $amout = $decoded_data['amount'];
                        $bankSender = strtoupper($decoded_data['sender_bank']);
                        $bankVee = FlipChannel::where('kode', $bankSender)->first();
                        $fee = $bankVee->fee;
                        $totalBayarTanpaFee = $amout - $fee;
                        //fee
                        //insert ke banking
                        $dataBanking = [
                            'banking_period_id' => $period->period_id,
                            //'banking_debit' => $decoded_data['amount'],
                            'banking_debit' => $totalBayarTanpaFee,
                            'banking_kredit' => 0,
                            'banking_date' => now($waktu),
                            'banking_code' => 1,
                            'banking_student_id' => $student->student_id,
                            'banking_note' => $note,
                            'user_user_id' => $student->student_id,

                        ];
                        //insert ke table banking
                        Banking::create($dataBanking);

                        //insert ke info app
                        $infoAppTab = [
                            'student_id' => $student->student_id,
                            'info' => 'Pembayaran top up tabungan atas nama ' . $student->student_full_name . '  dengan nominal ' . $totalBayarTanpaFee,
                            'created_at' => now()
                        ];
                        InfoApp::create($infoAppTab);

                        $waktu = $student->waktu_indonesia;
                        $waktuAsli = Carbon::now('Asia/jakarta')->format('Y-m-d H:i:s');
                        //  dd($waktuAsli);
                        //dd($waktu);

                        if ($waktu == 'WIT') {
                            $waktuAsli = Carbon::now('Asia/Makassar')->format('Y-m-d H:i:s');
                        }
                        if ($waktu == 'WITA') {
                            $waktuAsli = Carbon::now('Asia/Jayapura')->format('Y-m-d H:i:s');
                        }
                        //masukkan kedalam jurnla umum
                        $Jurnalumum = [
                            'sekolah_id' => $student->majors_majors_id,
                            'keterangan' => 'setoran tabungan ' . $student->student_full_name,
                            'noref' => $update->noref,
                            'tanggal' => Carbon::now()->format('Y-m-d'),
                            'pencatatan' => 'auto',
                            'waktu_update' => $waktuAsli,
                            'keterangan_lainnya' => 'tabungan',
                        ];
                        //JurnalUmum::create($Jurnalumum);
                        $idJurnal1 = JurnalUmum::insertGetId($Jurnalumum);
                        $accountCode = AkunIpayMu::where('unit_id', $student->majors_majors_id)->where('tipe', 'tabungan')->first();
                        $getAccountCode = $accountCode->akun_id;

                        $kodeAkun = Account::where('account_id', $getAccountCode)->first();
                        $getKodeAkun = $kodeAkun->account_code;

                        //sender bank
                        $bank = strtoupper($decoded_data['sender_bank']);

                        $fee = FlipChannel::where('kode', $bank)->first();
                        $debet = $decoded_data['amount'] - $fee->fee;

                        // masuk ke jurnal umum detail
                        $debitTabungan = [
                            'id_jurnal' => $idJurnal1,
                            'account_code' => $getKodeAkun,
                            'debet' => $debet,
                            'kredit' => 0.00
                        ];
                        JurnalUmumDetail::create($debitTabungan);

                        $akunKredit = Account::where('account_category', '!=', 0)
                            ->where('account_code', 'like', '2-%')
                            ->where('account_description', 'like', '%Tabungan%')
                            ->where('account_majors_id', $student->majors_majors_id)
                            ->get()
                            ->first()
                            ->account_code;

                        //$getAkunKredit = $akunKredit['account_code'];
                        $kreditTabungan = [
                            'id_jurnal' => $idJurnal1,
                            'account_code' => $akunKredit,
                            'debet' => 0.00,
                            'kredit' => $debet
                        ];
                        JurnalUmumDetail::create($kreditTabungan);

                        return response()->json([
                            'is_correct' => 'success',
                            'message' => 'Callback data decoded successfully.',
                            'data' => $decoded_data,
                            'account_code' => $getAccountCode,
                            'kode_akun' => $getKodeAkun,
                            'bank' => $bank,
                            'akunKredit' => $akunKredit
                            //'nama_bank' => $banking1
                            // 'data1' => $data1,
                            // 'note' => $note
                        ], 200);
                        //update status pembayaran fi flip_transaksi berdasarkan $status
                    } elseif ($kodePembayaran == 3) {

                        $sekolah = Sekolah::where('kode_sekolah', $kodePesantren)->first();

                        if (!$sekolah) {
                            return response()->json(['error' => 'Sekolah tidak ditemukan.'], 404);
                        }
                        $sekolahModel = new Sekolah();
                        $sekolahModel->setDatabaseName($sekolah->db);
                        // dd($sekolahModel);
                        $sekolahModel->switchDatabase();

                        $amout = $decoded_data['amount'];
                        $bankSender = strtoupper($decoded_data['sender_bank']);
                        $bankVee = FlipChannel::where('kode', $bankSender)->first();
                        $fee = $bankVee->fee;
                        $totalBayarTanpaFee = $amout - $fee;

                        $dataCallbackDonasi = [
                            'trx_id' => $decoded_data['id'],
                            'bill_id' => $decoded_data['bill_link_id'],
                            'bill_link' => $decoded_data['bill_link'],
                            'bill_title' => $decoded_data['bill_title'],
                            'sender_bank_type' => $decoded_data['sender_bank_type'],
                            'sender_bank' => $decoded_data['sender_bank'],
                            //'amount' => $decoded_data['amount'],
                            'amount' => $totalBayarTanpaFee,
                            'status' => $decoded_data['status'],
                            'sender_name' => $decoded_data['sender_name'],
                            'sender_email' => $decoded_data['sender_email'],
                        ];



                        $bill_id = $decoded_data['bill_link_id'];
                        FlipCallbackDonasi::create($dataCallbackDonasi);

                        //update data nya
                        $trxId = $decoded_data['bill_link_id'];
                        $flipDonasi = FlipDonasi::where('transactionId', $trxId)->first();

                        $updateDonasi = Donasi::where('donasi_ref_id', $flipDonasi->noref)->first();
                        $updateDonasi->donasi_status = 1;
                        $updateDonasi->save();


                        //flip donasi update
                        if ($flipDonasi) {
                            $flipDonasi->status = $decoded_data['status'];
                            $flipDonasi->di_bayar = $totalBayarTanpaFee;
                            $flipDonasi->save();
                        }

                        //insert ke info app
                        $parts = explode('|', $data1);
                        $idProgram = trim($parts[3]);
                        $nis = trim($parts[1]);
                        $student = Student::where('student_nis', $nis)->first();
                        $waktu = $student->waktu_indonesia;
                        $waktuAsli = Carbon::now('WIB');


                        if ($waktu == 'WIT') {
                            $waktuAsli = Carbon::now('WIT');
                        }
                        if ($waktu == 'WITA') {
                            $waktuAsli = Carbon::now('WITA');
                        }

                        $dataNotif = [
                            'student_id' => $student->student_id,
                            'info' => 'Pembayaran Donasi Berhasil',
                            'created_at' => Carbon::now()->format('Y-m-d')
                        ];

                        InfoApp::create($dataNotif);
                        $nama_sekolah = $sekolah->nama_sekolah;
                        $tanggal = Carbon::now()->format('Y-m-d');
                        $nama = $student->student_full_name;
                        $totalBayarFormatted = 'Rp. ' . number_format($decoded_data['amount'], 0, ',', '.');

                        //cari no va berdasarkan
                        $noVa = $flipDonasi->va_no;
                        $bank = $flipDonasi->va_bank;
                        $expired = $flipDonasi->Expired;
                        $namaProgram = Program::where('program_id', $kodeDonasi)->first();
                        $getNamaProgram = $namaProgram->program_name;
                        //notif wa berhasil membayrakan donasi
                        $pesan = <<<EOT
                        Bagian Administrasi  $nama_sekolah

                        Assalamualaikum warahmatullahi wabarakatuh,

                        Tanggal: {$tanggal}

                        Yth. Ayah/Bunda dari ananda {$nama} ,

                        Terima kasih telah menggunakan aplikasi ePesantren. Berikut detail pembayaran donasi yang telah dilakukan:


                        Total                 : {$totalBayarFormatted}
                        Nomor VA        : {$noVa}
                        Bank                 : {$bank}
                        Nama Donasi : {$getNamaProgram}

                        Hormat kami,
                        Bagian Administrasi
                        EOT;

                        $whatsappService = new WhatsappServicePembayaran();
                        //$nowa = $user->student_parent_phone;
                        $defaultnoWa = $student->student_parent_phone;
                        $nowa = $defaultnoWa;
                        $whatsappService->kirimPesan($nowa, $pesan);

                        //insert ke program
                        $data1 = $decoded_data['bill_title'];

                        $parts = explode('|', $data1);
                        $idProgram = trim($parts[3]);
                        $nis = trim($parts[1]);
                        $student = Student::where('student_nis', $nis)->first();
                        $waktu = $student->waktu_indonesia;
                        //update program earn
                        // $earnNow = $program->program_earn;

                        $program = Program::where('program_id', $idProgram)->first();
                        //update nilai program earn yang di dapat jadi jumlahkan program_earn yang ada dengan $totalBayarTanpaFee

                        if ($program) {
                            // Tambahkan nilai $totalBayarTanpaFee ke nilai program_earn yang ada
                            $program->program_earn += $totalBayarTanpaFee;
                            $program->program_updated_at = now($waktu);

                            $program->save();
                        }

                        $sekolahId = $student->majors_majors_id;

                        //noref terdiri dari DN ,nis, tanggal ,bulan ,tahun, idProgram
                        //kas
                        $tanggal = Carbon::now()->format('d');
                        $bulan = Carbon::now()->format('m');
                        $tahun = Carbon::now()->format('y');
                        $programId = $program->program_id;

                        $noref = $nis . $tanggal . $bulan . $tahun . $programId;
                        $kasPeriod = Period::where('period_status', 1)->first();
                        $kasAccountId = AkunIpayMu::where('unit_id', $student->majors_majors_id)->where('tipe', 'donasi')->first();
                        $dataKas = [
                            'kas_noref' => 'DN' . $noref,
                            'kas_period' => $kasPeriod->period_id,
                            'kas_date' => Carbon::now()->format('Y-m-d'),
                            'kas_month_id' => Carbon::now()->format('m'),
                            'kas_account_id' => $kasAccountId->akun_id,
                            'kas_majors_id' => $student->majors_majors_id,
                            'kas_note' => 'Donasi ' . $program->program_name,
                            'kas_category' => 3,
                            'kas_receipt' => ' ',
                            'kas_tax_receipt' => ' ',
                            'kas_debit' => $totalBayarTanpaFee,
                            'kas_kredit' => 0,
                            'kas_user_id' => $student->student_id,
                            'kas_input_date' => Carbon::now()->format('Y-m-d'),
                            'kas_last_update' => Carbon::now()->format('Y-m-d')
                        ];

                        Kas::create($dataKas);
                        //insert ke jurnal umum
                        $dataJurnalUmum1 = [
                            'sekolah_id' => $sekolahId,
                            'keterangan' => 'Pembayaran Donasi untuk program' . $program->program_name,
                            'noref' => 'DN' . $noref,
                            'tanggal' => Carbon::now()->format('Y-m-d'),
                            'pencatatan' => 'auto',
                            'waktu_update' => $waktuAsli,
                            'keterangan_lainnya' => 'Donasi program ' . $program->program_name
                        ];
                        //$idJurnal = JurnalUmum::insertGetId($dataJurnalUmum);
                        $idJurnal1 = JurnalUmum::insertGetId($dataJurnalUmum1);


                        // //insert jurnal umum detail debit
                        $accountDonasi = AkunIpayMu::where('unit_id', $student->majors_majors_id)->where('tipe', 'donasi')->first();
                        $accountDonasiId = $accountDonasi->akun_id;

                        //dapatkan account code nya
                        $accountCodeDonasi = Account::where('account_id', $accountDonasiId)->first();
                        $getAccountDonasi = $accountCodeDonasi->account_code;
                        $debitDonasi = [
                            'id_jurnal' => $idJurnal1,
                            'account_code' => $getAccountDonasi,
                            'debet' => $totalBayarTanpaFee,
                            'kredit' => 0.00
                        ];
                        JurnalUmumDetail::create($debitDonasi);

                        // //jurnal umum kredit

                        // //dapatkan account account id nya
                        $kreditAccount = Program::where('program_id', $kodeDonasi)->first();
                        $getKreditAccount = $kreditAccount->account_account_id;

                        //dapatkan account code nya
                        $kreditAccountCode = Account::where('account_id', $getKreditAccount)->first();
                        $getKreditAccountCode = $kreditAccountCode->account_code;

                        $dataKreditDonasi = [
                            'id_jurnal' => $idJurnal1,
                            'account_code' => $getKreditAccountCode,
                            'debet' => 0.00,
                            'kredit' => $totalBayarTanpaFee
                        ];

                        JurnalUmumDetail::create($dataKreditDonasi);


                        return response()->json([
                            'is_correct' => 'success',
                            'message' => 'Callback data decoded successfully.',
                            'data' => $decoded_data,
                            'id_program' => $idProgram,
                            'flipDonasi' => $flipDonasi
                            //'earn_now' => $earnNow
                            //'nama_bank' => $banking1
                            // 'data1' => $data1,
                            // 'note' => $note
                        ], 200);
                    }
                } else { //ini response gagal
                    if ($kodePembayaran == 3) {

                        //switch db nya
                        $sekolah = Sekolah::where('kode_sekolah', $kodePesantren)->first();

                        if (!$sekolah) {
                            return response()->json(['error' => 'Sekolah tidak ditemukan.'], 404);
                        }
                        $sekolahModel = new Sekolah();
                        $sekolahModel->setDatabaseName($sekolah->db);
                        // dd($sekolahModel);
                        $sekolahModel->switchDatabase();

                        $amout = $decoded_data['amount'];
                        $bankSender = strtoupper($decoded_data['sender_bank']);
                        $bankVee = FlipChannel::where('kode', $bankSender)->first();
                        $fee = $bankVee->fee;
                        $totalBayarTanpaFee = $amout - $fee;

                        //masukkan ke dalam flip_callback donasi
                        $dataCallbackDonasi = [
                            'trx_id' => $decoded_data['id'],
                            'bill_id' => $decoded_data['bill_link_id'],
                            'bill_link' => $decoded_data['bill_link'],
                            'bill_title' => $decoded_data['bill_title'],
                            'sender_bank_type' => $decoded_data['sender_bank_type'],
                            'sender_bank' => $decoded_data['sender_bank'],
                            //'amount' => $decoded_data['amount'],
                            'amount' => $totalBayarTanpaFee,
                            'status' => $decoded_data['status'],
                            'sender_name' => $decoded_data['sender_name'],
                            'sender_email' => $decoded_data['sender_email'],
                        ];

                        FlipCallbackDonasi::create($dataCallbackDonasi);
                        //update status flip donasi pembayaran menjadi
                        $trxId = $decoded_data['bill_link_id'];
                        $flipDonasi = FlipDonasi::where('transactionId', $trxId)->first();
                        //flip donasi update
                        if ($flipDonasi) {
                            $flipDonasi->status = $decoded_data['status'];

                            $flipDonasi->save();
                        }


                        //masuk ke notifikasi appnotifikasi
                        //insert ke program
                        $data1 = $decoded_data['bill_title'];

                        $parts = explode('|', $data1);
                        $idProgram = trim($parts[3]);
                        $nis = trim($parts[1]);
                        $student = Student::where('student_nis', $nis)->first();
                        $waktu = $student->waktu_indonesia;
                        $dataNotif = [
                            'student_id' => $student->student_id,
                            'info' => 'Pembayaran Donasi  gagal',
                            'created_at' => Carbon::now()->format('Y-m-d')
                        ];
                        InfoApp::create($dataNotif);

                        $noVa = $flipDonasi->va_no;
                        $bank = $flipDonasi->va_bank;
                        $expired = $flipDonasi->Expired;
                        $namaProgram = Program::where('program_id', $kodeDonasi)->first();
                        $getNamaProgram = $namaProgram->program_name;


                        $nama_sekolah = $sekolah->nama_sekolah;
                        $tanggal = Carbon::now()->format('Y-m-d');
                        $nama = $student->student_full_name;
                        $totalBayarFormatted = 'Rp. ' . number_format($decoded_data['amount'], 0, ',', '.');

                        //notif wa berhasil membayrakan donasi
                        $pesan = <<<EOT
                        Bagian Administrasi  $nama_sekolah

                        Assalamualaikum warahmatullahi wabarakatuh,

                        Tanggal: {$tanggal}

                        Yth. Ayah/Bunda dari ananda {$nama} ,

                        Terima kasih telah menggunakan aplikasi ePesantren. Berikut detail pembayaran donasi yang telah dilakukan dan gagal:


                        Total                 : {$totalBayarFormatted}
                        Nomor VA        : {$noVa}
                        Bank                 : {$bank}
                        Nama Donasi : {$getNamaProgram}

                        Hormat kami,
                        Bagian Administrasi
                        EOT;

                        $whatsappService = new WhatsappServicePembayaran();
                        //$nowa = $user->student_parent_phone;
                        $defaultnoWa = $student->student_parent_phone;
                        $nowa = $defaultnoWa;
                        $whatsappService->kirimPesan($nowa, $pesan);

                        return response()->json([
                            'is_correct' => 'error',
                            'message' => 'Invalid token.',
                            'data' => $decoded_data
                        ], 403);
                    }
                    if ($kodePembayaran == 2) {
                        $sekolah = Sekolah::where('kode_sekolah', $kodePesantren)->first();

                        if (!$sekolah) {
                            return response()->json(['error' => 'Sekolah tidak ditemukan.'], 404);
                        }
                        $sekolahModel = new Sekolah();
                        $sekolahModel->setDatabaseName($sekolah->db);
                        // dd($sekolahModel);
                        $sekolahModel->switchDatabase();

                        $amout = $decoded_data['amount'];
                        $bankSender = strtoupper($decoded_data['sender_bank']);
                        $bankVee = FlipChannel::where('kode', $bankSender)->first();
                        $fee = $bankVee->fee;
                        $totalBayarTanpaFee = $amout - $fee;

                        //masukkan ke dalam flip_callback donasi
                        $dataCallbackTabungan = [
                            'trx_id' => $decoded_data['id'],
                            'bill_id' => $decoded_data['bill_link_id'],
                            'bill_link' => $decoded_data['bill_link'],
                            'bill_title' => $decoded_data['bill_title'],
                            'sender_bank_type' => $decoded_data['sender_bank_type'],
                            'sender_bank' => $decoded_data['sender_bank'],
                            //'amount' => $decoded_data['amount'],
                            'amount' => $totalBayarTanpaFee,
                            'status' => $decoded_data['status'],
                            'sender_name' => $decoded_data['sender_name'],
                            'sender_email' => $decoded_data['sender_email'],
                        ];

                        FlipCallbackTabungan::create($dataCallbackTabungan);

                        //update ke table flip_transaksi_tabungan
                        $trxId = $decoded_data['bill_link_id'];
                        $statusBaru = $decoded_data['status'];

                        $flipTabungan = FlipTransaksiTabungan::where('transactionId', $trxId)->first();
                        if ($flipTabungan) {
                            $flipTabungan->status = $statusBaru;
                            $flipTabungan->save();
                        }


                        //masuk ke info app
                        $data1 = $decoded_data['bill_title'];

                        $parts = explode('|', $data1);
                        $idProgram = trim($parts[3]);
                        $nis = trim($parts[1]);
                        $student = Student::where('student_nis', $nis)->first();
                        $waktu = $student->waktu_indonesia;
                        $infoApp = [
                            'student_id' => $student->student_id,
                            'info' => 'Top up Tabungan gagal sebesar ' . $totalBayarTanpaFee . ' atas nama ' . $student->student_full_name,
                            'created_at' => Carbon::now()->format('Y-m-d')
                        ];
                        InfoApp::create($infoApp);


                        //notif wa berhasil membayrakan donasi
                        $nama_sekolah = $sekolah->nama_sekolah;
                        $noVa = $flipTabungan->va_no;
                        $bank = $flipTabungan->va_bank;

                        $tanggal = Carbon::now()->format('Y-m-d');
                        $nama = $student->student_full_name;
                        $totalBayarFormatted = 'Rp. ' . number_format($decoded_data['amount'], 0, ',', '.');

                        $pesan = <<<EOT
                        Bagian Administrasi  $nama_sekolah

                        Assalamualaikum warahmatullahi wabarakatuh,

                        Tanggal: {$tanggal}

                        Yth. Ayah/Bunda dari ananda {$nama} ,

                        Terima kasih telah menggunakan aplikasi ePesantren. Berikut detail Top Up tabungan yang telah dilakukan dan gagal:


                        Total                 : {$totalBayarFormatted}
                        Nomor VA        : {$noVa}
                        Bank                 : {$bank}


                        Hormat kami,
                        Bagian Administrasi
                        EOT;

                        $whatsappService = new WhatsappServicePembayaran();
                        //$nowa = $user->student_parent_phone;
                        $defaultnoWa = $student->student_parent_phone;
                        $nowa = $defaultnoWa;
                        $whatsappService->kirimPesan($nowa, $pesan);

                        //masukkan data ke flip transaksi tabungan
                        return response()->json([
                            'is_correct' => 'error',
                            'message' => 'Invalid token.',
                            'data' => $decoded_data,
                            'flip' => $flipTabungan,
                            'trx' => $trxId,

                        ], 403);
                    }
                    if ($kodePembayaran == 1) {
                        $sekolah = Sekolah::where('kode_sekolah', $kodePesantren)->first();

                        if (!$sekolah) {
                            return response()->json(['error' => 'Sekolah tidak ditemukan.'], 404);
                        }
                        $sekolahModel = new Sekolah();
                        $sekolahModel->setDatabaseName($sekolah->db);
                        // dd($sekolahModel);
                        $sekolahModel->switchDatabase();

                        $amout = $decoded_data['amount'];
                        $bankSender = strtoupper($decoded_data['sender_bank']);
                        $bankVee = FlipChannel::where('kode', $bankSender)->first();
                        $fee = $bankVee->fee;
                        $totalBayarTanpaFee = $amout - $fee;

                        $data1 = $decoded_data['bill_title'];
                        $parts = explode('|', $data1);
                        $flipNoTrans = trim($parts[3]);
                        //masukkan ke dalam flip_callback donasi
                        $dataCallback = [
                            'trx_id' => $decoded_data['id'],
                            'bill_id' => $decoded_data['bill_link_id'],
                            'bill_link' => $decoded_data['bill_link'],
                            'bill_title' => $decoded_data['bill_title'],
                            'sender_bank_type' => $decoded_data['sender_bank_type'],
                            'sender_bank' => $decoded_data['sender_bank'],
                            //'amount' => $decoded_data['amount'],
                            'amount' => $totalBayarTanpaFee,
                            'status' => $decoded_data['status'],
                            'sender_name' => $decoded_data['sender_name'],
                            'sender_email' => $decoded_data['sender_email'],
                        ];

                        FlipCallback::create($dataCallback);

                        //update data di flip_transaksi
                        $trxId = $decoded_data['bill_link_id'];
                        $flipPembayaran = FlipTransaksi::where('transactionId', $trxId)->first();

                        if ($flipPembayaran) {
                            $flipPembayaran->status = $decoded_data['status'];

                            $flipPembayaran->save();
                        }

                        $nis = trim($parts[1]);
                        $student = Student::where('student_nis', $nis)->first();
                        //masukkan ke info app
                        $infoApp = [
                            'student_id' => $student->student_id,
                            'info' => 'Pembayaran dengan no va ' . $flipPembayaran->va_no . 'Gagal',
                            'created_at' => Carbon::now()->format('Y-m-d')
                        ];

                        InfoApp::create($infoApp);
                        $flipNoTrans = is_array($flipNoTrans) ? $flipNoTrans : [$flipNoTrans];
                        //mencari bulan_id
                        //  $bulans = Bulan::whereIn('flip_no_trans', $flipNoTrans)->pluck('bulan_id')->toArray();
                        //update di table bulan berdasarkan bulan_id yang ada pada $bulan

                        //update tabele bebas pay
                        // $bebasPays = BebasPay::whereIn('flip_no_trans', $flipNoTrans)->pluck('bulan_id')->toArray();

                        // BebasPay::whereIn('bebas_pay_id', $bebasPays)
                        //     ->update([
                        //         'flip_no_trans' => null,
                        //         'flip_status' => null
                        //     ]);

                        // Bulan::whereIn('bulan_id', $bulans)
                        //     ->update([
                        //         'flip_no_trans' => null,
                        //         'flip_status' => null
                        //     ]);

                        //notif wa
                        $nama_sekolah = $sekolah->nama_sekolah;
                        $vaNo = $flipPembayaran->va_no;
                        $bank = $flipPembayaran->va_bank;


                        $tanggal = Carbon::now()->format('Y-m-d');
                        $nama = $student->student_full_name;
                        $totalBayarFormatted = 'Rp. ' . number_format($decoded_data['amount'], 0, ',', '.');

                        $pesan = <<<EOT
                        Bagian Administrasi  $nama_sekolah

                        Assalamualaikum warahmatullahi wabarakatuh,

                        Tanggal: {$tanggal}

                        Yth. Ayah/Bunda dari ananda {$nama} ,

                        Terima kasih telah menggunakan aplikasi ePesantren. Berikut detail pembayran yang telah dilakukan dan gagal:


                        Total                 : {$totalBayarFormatted}
                        Nomor VA        : {$vaNo}
                        Bank                 : {$bank}

                        Silahkan mencoba kembali
                        Hormat kami,
                        Bagian Administrasi
                        EOT;

                        $whatsappService = new WhatsappServicePembayaran();
                        //$nowa = $user->student_parent_phone;
                        $defaultnoWa = $student->student_parent_phone;
                        $nowa = $defaultnoWa;
                        $whatsappService->kirimPesan($nowa, $pesan);


                        return response()->json([
                            'is_correct' => false,
                            'data' => $decoded_data,
                            'flip_no_trans' => $flipNoTrans,
                            //'bulan_id' => $bulan
                        ]);
                    }
                }
            } else {
                return response()->json([
                    'is_correct' => 'error',
                    'message' => 'Invalid token.',
                ], 403);
            }
        }
    }
}
