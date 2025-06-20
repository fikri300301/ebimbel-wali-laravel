<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Kas;
use App\Models\Pos;
use App\Models\Bebas;
use App\Models\Bulan;
use App\Models\major;
use App\Models\LogTrx;
use App\Models\Period;
use App\Models\Account;
use App\Models\Banking;
use App\Models\InfoApp;
use App\Models\Payment;
use App\Models\Sekolah;
use App\Models\Student;
use App\Models\BebasPay;
use App\Models\BniConfig;
use App\Models\AkunIpayMu;
use App\Models\JurnalUmum;
use App\Models\FlipChannel;
use App\Models\FlipCallback;
use Illuminate\Http\Request;
use App\Models\FlipTransaksi;
use App\Models\BebasPayMobile;
use App\Models\BniTrx;
use App\Models\JurnalUmumDetail;
use App\Models\DataLogCallbackBni;
use App\Services\DatabaseSwitcher;
use Illuminate\Support\Facades\Log;
use App\Models\FlipCallbackTabungan;
use App\Models\FlipTransaksiTabungan;
use App\Services\WhatsappServicePembayaran;

class BniCallbackController extends Controller
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

    public function bniCallback(Request $request)
    {
        $data = $request->input('data') ?: null;
        $token = $request->input('token') ?: null;

        $data1 = $data['bill_title'];
        $parts = explode('|', $data1);

        $kodePesantren = trim($parts[2]);
        $kodePembayaran = trim($parts[4]);
        $idTransaksi = trim($parts[3]);
        $getWaktu = trim($parts[5]);
        $nis = trim($parts[1]);

        $sekolah = Sekolah::where('kode_sekolah', $kodePesantren)->first();
        if (!$sekolah) {
            return response()->json(['error' => 'Sekolah tidak ditemukan.'], 404);
        }
        $sekolahModel = new Sekolah();
        $this->databaseSwitcher->switchDatabase($sekolah->db, $sekolahModel);

        //token bni harus disesuaikan dulu dengan token BNI nya

        //ambil dulu nisa nya
        $student = Student::where('student_nis', $nis)->first();
        $majors = $student->majors_majors_id;

        $tokenBni = BniConfig::where('majors_id', $majors)->where('is_active', 1)->first();
        // dd($tokenBni->secret_key);
        if ($token === $tokenBni->secret_key) {
            //  dd($data);
            //masukkan kedalam log bni callback
            $dataLogBni = [
                'bill_id' => $data['bill_id'],
                'bill_link' => $data['bill_link'],
                'bill_title' => $data['bill_title'],
                'sender_bank_type' => $data['sender_bank_type'],
                'sender_bank' => $data['sender_bank'],
                'amount' => $data['amount'],
                'status' => $data['status'],
                'sender_name' => $data['sender_name'],
                'sender_email' => $data['sender_email']
            ];

            //ini nanti di nyalakan
            DataLogCallbackBni::create($dataLogBni);

            if ($data['status'] == 'SUCCESSFUL') {
                //   dd($data);
                if ($kodePembayaran == 1) {
                    $sekolah = Sekolah::where('kode_sekolah', $kodePesantren)->first();
                    if (!$sekolah) {
                        return response()->json(['error' => 'Sekolah tidak ditemukan.'], 404);
                    }
                    $sekolahModel = new Sekolah();
                    $this->databaseSwitcher->switchDatabase($sekolah->db, $sekolahModel);
                    // update trx
                    $bniTrx = $data['bill_id'];
                    $updateBniTrx = BniTrx::where('noref', $bniTrx)->first();
                    if ($updateBniTrx) {
                        $updateBniTrx->update([
                            'trx_status' => $data['status'] == 'SUCCESSFUL' ? 'PAID' : 'OTHER_STATUS',
                            'payment_amount' => $data['amount'],
                            'cumulative_payment_amount' => $data['amount'],
                        ]);
                    } else {
                        return response()->json(['error' => 'Data BNI tidak ditemukan.'], 404);
                    }

                    $dataCallback = [
                        'bill_id' => $data['bill_id'],
                        'bill_link' => $data['bill_link'],
                        'bill_title' => $data['bill_title'],
                        'sender_bank_type' => $data['sender_bank_type'],
                        'sender_bank' => $data['sender_bank'],
                        'amount' => $data['amount'],
                        'status' => $data['status'],
                        'sender_name' => $data['sender_name'],
                        'sender_email' => $data['sender_email']
                    ];
                    $bill_id = $data['bill_id'];
                    FlipCallback::create($dataCallback);
                    $responStatus = FlipCallback::where('bill_id', $bill_id)->first();
                    $status = $responStatus->status;
                    $update = FlipTransaksi::where('id_transaksi', $idTransaksi)->first();
                    // dd($update);
                    $student = Student::where('student_nis', $nis)->first();
                    $major = $student->majors_majors_id;
                    $majorId = major::where('majors_id', $major)->first();
                    $majorName = $majorId->majors_short_name;
                    $like = 'SP' . str_replace(" ", "", $majorName . $nis);
                    $idMajors = $major;
                    $noref = Kas::getNoref($like, $idMajors);
                    $paymentNoref = $bill_id;

                    $noTrans = $update->id_transaksi;
                    // dd($noTrans);
                    if ($update && $paymentNoref) {
                        // Update status pembayaran pada flip_transaksi
                        $update->di_bayar =  $data['amount'];
                        $update->noref = $paymentNoref;
                        $update->status = $status;
                        $update->save();
                    } else {
                        // Handle jika transaksi tidak ditemukan
                        // Misalnya, bisa log atau memberikan respon error
                        Log::error("Transaksi dengan ID $bill_id tidak ditemukan.");
                    }

                    $amount = $data['amount'];
                    $bankSender = strtoupper($data['sender_bank']);
                    $bankVee = FlipChannel::where('kode', $bankSender)->first();
                    $fee = $bankVee->fee;
                    $totalBayarTanpaFee = $amount - $fee;

                    $waktu = $getWaktu;
                    $bulan = Bulan::where('flip_no_trans', $update->id_transaksi)->get();
                    $statusFlip = 'LUNAS';
                    $statusBulan = 1;
                    $userId = $student->student_id;
                    $major = $student->majors_majors_id;
                    $akunIpaymu = AkunIpayMu::where('unit_id', $major)->where('tipe', 'pembayaran')->first();

                    $akunId = $akunIpaymu->akun_id;

                    $idTransaksiPesan = $update->id_transaksi;

                    if ($bulan->count() > 0) {

                        foreach ($bulan as $value) {
                            $value->update([
                                'bulan_account_id' => $akunId,
                                'flip_status' => $statusFlip,
                                'bulan_noref' =>  $bill_id,
                                'bulan_status' => $statusBulan,
                                'bulan_date_pay' => now($waktu),
                                //'user_user_id' => $userId,
                            ]);
                        }
                    }

                    $bebasPayMobile = BebasPayMobile::where('flip_no_trans', $idTransaksi)->get();
                    $BebasNoRef = $paymentNoref;

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
                    $totalBayarFormatted = 'Rp. ' . number_format($amount, 0, ',', '.');
                    $va = FlipTransaksi::where('id_transaksi', $noTrans)->first();
                    $pesan = <<<EOT
                    Bagian Administrasi  {$sekolah->nama_sekolah}

                    Assalamualaikum warahmatullahi wabarakatuh,

                    Tanggal: {$tanggal}

                    Yth. Ayah/Bunda dari ananda {$student->student_full_name},

                    Terima kasih telah menggunakan aplikasi ePesantren. Berikut detail pembayaran yang berhasil:

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

                    //  dd($accountCode);
                    if (($accountCode && $accountCode->account_code) || ($bebasAccountCode && $bebasAccountCode->account_code)) {
                        // dd($accountCode);
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
                    }

                    return response()->json([
                        'is_correct' => 'success',
                        'message' => 'Callback data decoded successfully.',
                        'data' => $data,
                        'kode_pesantren' => $kodePesantren,
                        'nis' => $nis,
                        //'noTrans' => $noTrans,
                        'status' => $status,
                    ]);
                } elseif ($kodePembayaran == 2) {
                    $sekolah = Sekolah::where('kode_sekolah', $kodePesantren)->first();
                    if (!$sekolah) {
                        return response()->json(['error' => 'Sekolah tidak ditemukan.'], 404);
                    }
                    $sekolahModel = new Sekolah();
                    $this->databaseSwitcher->switchDatabase($sekolah->db, $sekolahModel);
                    // dd($data);
                    $dataCallbackTabungan = [
                        'bill_id' => $data['bill_id'],
                        'bill_link' => $data['bill_link'],
                        'bill_title' => $data['bill_title'],
                        'sender_bank_type' => $data['sender_bank_type'],
                        'sender_bank' => $data['sender_bank'],
                        'amount' => $data['amount'],
                        'status' => $data['status'],
                        'sender_name' => $data['sender_name'],
                        'sender_email' => $data['sender_email']
                    ];

                    FlipCallbackTabungan::create($dataCallbackTabungan);
                    $bill_id = $data['bill_id'];
                    $responseStatus = FlipCallbackTabungan::where('bill_id', $bill_id)->first();
                    $status = $responseStatus->status;
                    $update = FlipTransaksiTabungan::where('noref', $bill_id)->first();
                    $student = Student::where('student_nis', $nis)->first();
                    $waktu = $student->waktu_indonesia ?? 'WIB';
                    //jika $waktu nya null beri nilai 'WIB'
                    if ($update) {
                        $update->status = $status;
                        $update->di_bayar = $data['amount'];
                        $update->save();
                    }
                    $getNote = $data['bill_title'];
                    $parts = explode('|', $getNote);
                    $note = trim($parts[3]);
                    //  dd($note);
                    $bankSender = strtoupper($data['sender_bank']);
                    $bankVee = FlipChannel::where('kode', $bankSender)->first();
                    $fee = $bankVee->fee;
                    $period = Period::where('period_status', 1)->first();
                    // $bankVee = Flip

                    $dataBanking = [
                        'banking_period_id' => $period->period_id,
                        //'banking_debit' => $decoded_data['amount'],
                        'banking_debit' => $data['amount'],
                        'banking_kredit' => 0,
                        'banking_date' => now($waktu),
                        'banking_code' => 1,
                        'banking_student_id' => $student->student_id,
                        'banking_note' => $note,
                        'user_user_id' => $student->student_id,

                    ];

                    Banking::create($dataBanking);
                    $infoAppTab = [
                        'student_id' => $student->student_id,
                        'info' => 'Top up tabungan atas nama ' . $student->student_full_name . '  dengan nominal ' . $data['amount'] . 'berhasil',
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

                    $idJurnal1 = JurnalUmum::insertGetId($Jurnalumum);
                    $accountCode = AkunIpayMu::where('unit_id', $student->majors_majors_id)->where('tipe', 'pembayaran')->first();

                    $getAccountCode = $accountCode->akun_id;
                    $kodeAkun = Account::where('account_id', $getAccountCode)->first();

                    $getKodeAkun = $kodeAkun->account_code;
                    $bank = strtoupper($data['sender_bank']);

                    $fee = FlipChannel::where('kode', $bank)->first();
                    $debet = $data['amount'] - $fee->fee;

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
                        'data' => $data,
                        'account_code' => $getAccountCode,
                        //'kode_akun' => $getKodeAkun,
                        'note' => $note,
                        // 'akunKredit' => $akunKredit
                        //'nama_bank' => $banking1
                        // 'data1' => $data1,
                        // 'note' => $note
                    ], 200);
                }
            }
        } else { //jika pembayaran callback nya gagal
            if ($kodePembayaran == 1) {
                $sekolah = Sekolah::where('kode_sekolah', $kodePesantren)->first();
                if (!$sekolah) {
                    return response()->json(['error' => 'Sekolah tidak ditemukan.'], 404);
                }
                $sekolahModel = new Sekolah();
                $this->databaseSwitcher->switchDatabase($sekolah->db, $sekolahModel);

                $dataCallback = [
                    'bill_id' => $data['bill_id'],
                    'bill_link' => $data['bill_link'],
                    'bill_title' => $data['bill_title'],
                    'sender_bank_type' => $data['sender_bank_type'],
                    'sender_bank' => $data['sender_bank'],
                    'amount' => $data['amount'],
                    'status' => $data['status'],
                    'sender_name' => $data['sender_name'],
                    'sender_email' => $data['sender_email']
                ];
                $bill_id = $data['bill_id'];
                FlipCallback::create($dataCallback);
                $responStatus = FlipCallback::where('bill_id', $bill_id)->first();
                $status = $responStatus->status;
                $update = FlipTransaksi::where('id_transaksi', $idTransaksi)->first();

                if ($responStatus) {
                    $update->status =  $data['status'];
                    $update->save();
                }

                $student = Student::where('student_nis', $nis)->first();
                $infoApp = [
                    'student_id' => $student->student_id,
                    'info' => 'Pembayaran dengan no va ' . $update->va_no . 'Gagal',
                    'created_at' => Carbon::now()->format('Y-m-d')
                ];
                InfoApp::create($infoApp);

                return response()->json([
                    'is_correct' => false,
                    'data' => $data,
                    'flip_no_trans' => $idTransaksi,
                    //'bulan_id' => $bulan
                ]);
            }
            if ($kodePembayaran == 2) {
                $sekolah = Sekolah::where('kode_sekolah', $kodePesantren)->first();
                if (!$sekolah) {
                    return response()->json(['error' => 'Sekolah tidak ditemukan.'], 404);
                }
                $sekolahModel = new Sekolah();
                $this->databaseSwitcher->switchDatabase($sekolah->db, $sekolahModel);

                $dataCallbackTabungan = [
                    'bill_id' => $data['bill_id'],
                    'bill_link' => $data['bill_link'],
                    'bill_title' => $data['bill_title'],
                    'sender_bank_type' => $data['sender_bank_type'],
                    'sender_bank' => $data['sender_bank'],
                    'amount' => $data['amount'],
                    'status' => $data['status'],
                    'sender_name' => $data['sender_name'],
                    'sender_email' => $data['sender_email']
                ];

                FlipCallbackTabungan::create($dataCallbackTabungan);
                $bill_id = $data['bill_id'];
                $responseStatus = FlipCallbackTabungan::where('bill_id', $bill_id)->first();
                $status = $responseStatus->status;
                $update = FlipTransaksiTabungan::where('noref', $bill_id)->first();

                if ($update) {
                    $update->status = $data['status'];
                    $update->save();
                }
                $student = Student::where('student_nis', $nis)->first();
                $waktu = $student->waktu_indonesia ?? 'WIB';

                $infoApp = [
                    'student_id' => $student->student_id,
                    'info' => 'Top up Tabungan gagal sebesar ' . $data['amount'] . ' atas nama ' . $student->student_full_name,
                    'created_at' => Carbon::now()->format('Y-m-d')
                ];
                InfoApp::create($infoApp);

                return response()->json([
                    'is_correct' => 'error',
                    'message' => 'Invalid token.',
                    'data' => $data,
                    'flip' => $update,

                ], 403);
            }
        }
    }
}
