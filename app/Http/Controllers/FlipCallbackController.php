<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Kas;
use App\Models\Pos;
use App\Models\Bebas;
use App\Models\Bulan;
use App\Models\major;
use App\Models\Donasi;
use App\Models\LogTrx;
use App\Models\Period;
use GuzzleHttp\Client;
use App\Models\Account;
use App\Models\Banking;
use App\Models\InfoApp;
use App\Models\Payment;
use App\Models\Program;
use App\Models\Sekolah;
use App\Models\Setting;
use App\Models\Student;
use App\Models\BebasPay;
use App\Models\AkunIpayMu;
use App\Models\FlipDonasi;
use App\Models\JurnalUmum;
use App\Models\FlipChannel;
use App\Models\FlipCallback;
use Illuminate\Http\Request;
use App\Models\FlipTransaksi;
use App\Models\BebasPayMobile;
use App\Models\JurnalUmumDetail;
use App\Models\FlipCallbackDonasi;
use App\Services\DatabaseSwitcher;
use Illuminate\Support\Facades\Log;
use App\Models\FlipCallbackTabungan;
use Illuminate\Support\Facades\Http;
use App\Models\FlipTransaksiTabungan;
use App\Services\WhatsappServicePembayaran;



class FlipCallbackController extends Controller
{
    protected $databaseSwitcher;

    public function __construct(DatabaseSwitcher $databaseSwitcher)
    {
        $this->databaseSwitcher = $databaseSwitcher;
    }
    function RemoveSpecialChar($str)
    {
        $res = preg_replace('/[^a-zA-Z0-9_ -]/s', '', $str);
        return $res;
    }

    public function paymentCallback(Request $request)
    {
        // Ambil data dan token dari request POST yang dikirim Flip
        //  error_log($request);
        try {
            $data = $request->input('data') ?: null;
            $token = $request->input('token') ?: null;

            $decoded_data = json_decode($data, true);
            $data1 = $decoded_data['bill_title'];
            $parts = explode('|', $data1);
            if (count($parts) < 6) { //perbaikan
                $client = new Client();
                $response = $client->request("POST", 'https://mobile.adminsekolah.net/rest-api-dev/callback_semua.php', ['form_params' => ['data' => $data, "token" => $token]]); //ini nanti diganti
                $responseBody = $response->getBody();
                $responseContent = $responseBody->getContents();
                $responseJson = json_decode($responseContent, true);

                if ($responseJson["is_correct"] == true) {

                    return response()->json([
                        'is_correct' => true,
                        'message' => 'Berhasil.',
                        "data" => $data,
                        'response' => $responseJson ?? "No Data"
                    ]);
                } else {
                    return response()->json([
                        'is_correct' => false,
                        'message' => 'Gagal.',

                    ]);
                }
            }
            $kodePesantren = trim($parts[2]);
            $sekolah = Sekolah::where('kode_sekolah', $kodePesantren)->first();



            if (!$sekolah) {
                return response()->json(['error' => 'Sekolah tidak ditemukan.'], 404);
            }
            $sekolahModel = new Sekolah();
            $this->databaseSwitcher->switchDatabase($sekolah->db, $sekolahModel); //tambhakan ini
            // $sekolahModel->setDatabaseName($sekolah->db);

            $waktusekolah = $sekolah->waktu_indonesia;
            $tokenDb = Setting::where('setting_name', 'token_validasi_test')->first(); //tokeb validasi test
            // $tokenDb = Setting::where('setting_name', 'token_validasi')->first(); //token validasi live


            // Verifikasi token (cocokkan dengan token yang diterima dari Flip Dashboard)
            // if ($token === $tokenDb->setting_value)
            //  if ($token === '$2y$13$airzQQ4ocKvjKB3zJyI/2.hOiiZywNLNigiBJlYP4Jq5ZhTFgBWPC') {
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
                            $this->databaseSwitcher->switchDatabase($sekolah->db, $sekolahModel);


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
                            if ($bankSender == 'BSM') {
                                $bankSender = 'BSI';
                            }
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
                                        //'user_user_id' => $userId,
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
                            // $pesan = <<<EOT
                            // Bagian Administrasi  {$sekolah->nama_sekolah}

                            // Assalamualaikum warahmatullahi wabarakatuh,

                            // Tanggal: {$tanggal}

                            // Yth. Ayah/Bunda dari ananda {$student->student_full_name},

                            // Terima kasih telah menggunakan aplikasi ePesantren. Berikut detail pembayaran yang berhasil:

                            // ID transaksi    : {$noTrans}
                            // Total                 : {$totalBayarFormatted}
                            // Nomor VA        : {$va->va_no}
                            // Bank                 : {$va->va_bank}



                            // Terimakah telah melakukan pembayaran.

                            // Hormat kami,
                            // Bagian Administrasi
                            // EOT;

                            // // notif wa pembayaran
                            // $whatsappService = new WhatsappServicePembayaran();
                            // $nowa = $student->student_parent_phone;
                            // $whatsappService->kirimPesan($nowa, $pesan);

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
                                    //'bebas_pay_bebas_pay_id' => $bebas->bebas_bebas_id,
                                    'bebas_pay_bebas_pay_id' => $bebas->bebas_pay_id,
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

                                $bulanbill = $accountCodeKredit->pluck('bulan_bill');
                                $payment = Payment::whereIn('payment_id', $paymentIds)->get();

                                //     // Loop untuk mengambil 'pos_pos_id' dari setiap elemen dalam array
                                $posKredit = Payment::whereIn('payment_id', $paymentIds)->get();
                                // $getPos1 = $posKredit->pluck('pos_pos_id');

                                //mecari bulan id


                                //payment mencari nilai pos_id
                                $getPos = [];
                                foreach ($paymentIds as $paymentId) {
                                    $posKredit = Payment::where('payment_id', $paymentId)->first();
                                    if ($posKredit) {
                                        $getPos[] = $posKredit->pos_pos_id;
                                    }
                                }

                                //pos mmencari nilai account_id
                                $getaccount = [];
                                foreach ($getPos as $getpo) {
                                    $posAccount = Pos::where('pos_id', $getpo)->first();
                                    if ($posAccount) {
                                        $getaccount[] = $posAccount->account_account_id;
                                    }
                                }

                                $getCode = [];
                                foreach ($getaccount as $getacc) {
                                    $code = Account::where('account_id', $getacc)->first();
                                    if ($code) {
                                        $getCode[] = $code->account_code;
                                    }
                                }

                                //masukka ke jurnal umum detail
                                if ($bulanbill) {
                                    foreach ($bulanbill as $index => $bulan) {
                                        $dataKreditBulan = [
                                            'id_jurnal' => $idJurnal,
                                            'account_code' => $getCode[$index] ?? null, // Pastikan ada nilai default jika index tidak ada
                                            'debet' => 0.00,
                                            'kredit' => $bulan
                                        ];

                                        JurnalUmumDetail::insert($dataKreditBulan);
                                    }
                                }

                                // $posPosIds = $payment->map(function ($paymentItem) {
                                //     return $paymentItem->pos_pos_id;
                                // })->toArray;

                                //     // nilai pos
                                // $accountId1 = Pos::whereIn('pos_id', $posPosIds)->get();
                                // $accountIds = $accountId1->map(function ($item) {
                                //     return $item->account_account_id;
                                // });

                                // //     //mencari nilai account code
                                // $accountCodes = Account::whereIn('account_id', $accountIds)->get();
                                // $getAccountCodes = $accountCodes->map(function ($item) {
                                //     return $item->account_code;
                                // });

                                // //     //mecari nilai pembayaran di dapat dari bulan id
                                // $payKredit = Bulan::whereIn('bulan_id', $bulanIds)->get();
                                // $getPayKredit = $payKredit->pluck('bulan_bill');
                                // $getPayKredit = $payKredit->map(function ($item) {
                                //     return $item->bulan_bill;
                                // });

                                // if ($getPayKredit->isNotEmpty()) {
                                //     $jurnalUmumDetailKreditbulan = $getAccountCodes->map(function ($accountCode, $index) use ($idJurnal, $getPayKredit) {
                                //         // Ambil nilai kredit sesuai dengan index
                                //         $kredit = $getPayKredit->get($index) ?? 0; // Jika index tidak ada, default ke 0
                                //         return [
                                //             'id_jurnal' => $idJurnal,
                                //             'account_code' => $accountCode,
                                //             'debet' => 0.00,
                                //             'kredit' => $kredit,
                                //         ];
                                //     });

                                //     JurnalUmumDetail::insert($jurnalUmumDetailKreditbulan->toArray());
                                // }
                            }

                            if ($cekBebas->isNotEmpty()) {
                                // Mencari bebas account_id (hasilnya array integer)
                                $bebasPayAccountIds = BebasPay::where('bebas_pay_noref', $paymentNoref)->pluck('bebas_pay_id')->toArray();

                                //mencari nilai bebas
                                $bebasId = BebasPay::whereIn('bebas_pay_id', $bebasPayAccountIds)->pluck('bebas_bebas_id')->toArray();


                                // //mencari bebas payment_id
                                $payment1 = Bebas::whereIn('bebas_id', $bebasId)->pluck('payment_payment_id')->toArray();


                                // //mencari pos id
                                $pos = Payment::whereIn('payment_id', $payment1)->pluck('pos_pos_id')->toArray();

                                // //mancari account id
                                $account = Pos::whereIn('pos_id', $pos)->pluck('account_account_id')->toArray();


                                // //mencari account code
                                $accountCodes1 = Account::whereIn('account_id', $account)->pluck('account_code')->toArray();


                                // // // Mendapatkan debet
                                $bebasBills = BebasPay::whereIn('bebas_pay_id', $bebasPayAccountIds)->get();
                                $getBebasBill = $bebasBills->pluck('bebas_pay_bill', 'bebas_pay_id')->toArray();


                                $jurnalUmumDetailBebas = [];
                                foreach ($accountCodes1 as $index => $accountCode) {
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
                                // 'payment_id' =>  $payment1,
                                // 'pos_id' => $pos,
                                // 'account' => $account,
                                // 'account_code' => $accountCodes1,
                                // 'bill_payment' => $getBebasBill,
                                // 'payment' => $payment,
                                //'paymentIds' => $paymentIds,
                                //  'get_pos' =>  $posKredit->pluck('pos_pos_id')->values(),
                                //    'bulan_id' =>
                                // 'get_pos2' => $getPos,
                                // 'get_account' => $getaccount,
                                // 'get_code' => $getCode,
                                // 'bulan_bill' => $bulanbill
                                // 'accountCode' => $getAccountCodes,
                                // 'bulan_ids' => $bulanIds

                            ], 200);
                        } elseif ($kodePembayaran == 2) {
                            $sekolah = Sekolah::where('kode_sekolah', $kodePesantren)->first();

                            if (!$sekolah) {
                                return response()->json(['error' => 'Sekolah tidak ditemukan.'], 404);
                            }
                            $sekolahModel = new Sekolah();
                            $this->databaseSwitcher->switchDatabase($sekolah->db, $sekolahModel);


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
                            if ($bankSender == 'BSM') {
                                $bankSender = 'BSI';
                            }
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
                                'info' => 'Top up tabungan atas nama ' . $student->student_full_name . '  dengan nominal ' . $totalBayarTanpaFee . 'berhasil',
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
                            $accountCode = AkunIpayMu::where('unit_id', $student->majors_majors_id)->where('tipe', 'pembayaran')->first();
                            $getAccountCode = $accountCode->akun_id;

                            // $accountCode2 = AkunIpayMu::where('unit_id', $student->majors_majors_id)->where('tipe', 'pembayaran')->first();
                            // $getAccountCode2 = $accountCode->akun_id;

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
                                ->first();

                            if (!$akunKredit) {
                                $akunKredit = Account::where('account_category', '!=', 0)
                                    ->where('account_code', 'like', '2-%')
                                    ->where('account_description', 'like', '%Hutang%')
                                    ->first();
                            }

                            $akunKreditCode = $akunKredit ? $akunKredit->account_code : null;

                            //$getAkunKredit = $akunKredit['account_code'];
                            $kreditTabungan = [
                                'id_jurnal' => $idJurnal1,
                                'account_code' => $akunKreditCode,
                                'debet' => 0.00,
                                'kredit' => $debet
                            ];
                            JurnalUmumDetail::create($kreditTabungan);

                            return response()->json([
                                'is_correct' => 'success',
                                'message' => 'Callback data decoded successfully.',
                                'data' => $decoded_data,
                                'account_code' => $getAccountCode,
                                //'kode_akun' => $getKodeAkun,
                                'note' => $note,
                                // 'akunKredit' => $akunKredit
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
                            $this->databaseSwitcher->switchDatabase($sekolah->db, $sekolahModel);

                            // dd($sekolahModel);
                            //  $sekolahModel->switchDatabase();

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
                            $this->databaseSwitcher->switchDatabase($sekolah->db, $sekolahModel);
                            // $sekolahModel->setDatabaseName($sekolah->db);
                            // dd($sekolahModel);
                            //$sekolahModel->switchDatabase();

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
                            $this->databaseSwitcher->switchDatabase($sekolah->db, $sekolahModel);
                            // $sekolahModel->setDatabaseName($sekolah->db);
                            // dd($sekolahModel);
                            //  $sekolahModel->switchDatabase();

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
                            $this->databaseSwitcher->switchDatabase($sekolah->db, $sekolahModel);
                            // $sekolahModel->setDatabaseName($sekolah->db);
                            // dd($sekolahModel);
                            // $sekolahModel->switchDatabase();

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
                            $bulans = Bulan::whereIn('flip_no_trans', $flipNoTrans)->get();
                            $bulanId = $bulans->pluck('bulan_id')->toArray();
                            if (!empty($bulanId)) {
                                Bulan::whereIn('bulan_id', $bulanId)
                                    ->update([
                                        'flip_no_trans' => null,
                                        'flip_status' => null
                                    ]);
                            }
                            //update tabele bebas pay
                            $bebasPays = BebasPayMobile::whereIn('flip_no_trans', $flipNoTrans)->get();
                            $bebasId = $bebasPays->pluck('bebas_pay_id')->toArray();
                            if (!empty($bebasId)) {
                                BebasPayMobile::whereIn('bebas_pay_id', $bebasId)
                                    ->update([
                                        'flip_no_trans' => null,
                                        'flip_status' => null
                                    ]);
                            }
                            //notif wa
                            $nama_sekolah = $sekolah->nama_sekolah;
                            $vaNo = $flipPembayaran->va_no;
                            $bank = $flipPembayaran->va_bank;


                            $tanggal = Carbon::now()->format('Y-m-d');
                            $nama = $student->student_full_name;
                            $totalBayarFormatted = 'Rp. ' . number_format($decoded_data['amount'], 0, ',', '.');

                            // $pesan = <<<EOT
                            // Bagian Administrasi  $nama_sekolah

                            // Assalamualaikum warahmatullahi wabarakatuh,

                            // Tanggal: {$tanggal}

                            // Yth. Ayah/Bunda dari ananda {$nama} ,

                            // Terima kasih telah menggunakan aplikasi ePesantren. Berikut detail pembayran yang telah dilakukan dan gagal:


                            // Total                 : {$totalBayarFormatted}
                            // Nomor VA        : {$vaNo}
                            // Bank                 : {$bank}

                            // Silahkan mencoba kembali
                            // Hormat kami,
                            // Bagian Administrasi
                            // EOT;

                            // $whatsappService = new WhatsappServicePembayaran();
                            // //$nowa = $user->student_parent_phone;
                            // $defaultnoWa = $student->student_parent_phone;
                            // $nowa = $defaultnoWa;
                            // $whatsappService->kirimPesan($nowa, $pesan);


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
        } catch (\Throwable $th) {
            // Handle the exception
            return response()->json([
                'is_correct' => 'error',
                'message' => 'An error occurred: ' . $th->getMessage(),
                'line' => $th->getLine(),
            ], 500);
        }
    }
}
//}
