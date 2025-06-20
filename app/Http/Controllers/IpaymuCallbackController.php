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
use App\Models\AkunIpayMu;
use App\Models\JurnalUmum;
use Illuminate\Http\Request;
use App\Models\BebasPayMobile;
use App\Models\IpaymuCallback;
use App\Models\IpaymuTransaksi;
use App\Models\JurnalUmumDetail;
use App\Services\DatabaseSwitcher;
use App\Models\data_ipaymu_channel;
use Illuminate\Support\Facades\Log;
use App\Models\IpaymuCallbackTabungan;
use App\Models\IpaymuTransaksiTabungan;
use App\Services\WhatsappServicePembayaran;

class IpaymuCallbackController extends Controller
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
    public function ipaymuCallback(Request $request)
    {
        $dataDb = $request->reference_id;
        $parts = explode('|', $dataDb);
        $kodeSekolah = trim($parts[0]);
        $nis = trim($parts[1]);
        $kodeBayar = trim($parts[2]);
        // $kodeProgram = trim($parts[3]);
        // $catatan = trim($parts[4]);
        $sekolah = Sekolah::where('kode_sekolah', $kodeSekolah)->first();

        if (!$sekolah) {
            return response()->json([
                'message' => 'Sekolah tidak ditemukan'
            ], 404);
        }

        $sekolahModel = new Sekolah();
        $this->databaseSwitcher->switchDatabase($sekolah->db, $sekolahModel);
        // $sekolahMOdel->setDatabaseName($sekolah->db);
        // $sekolahMOdel->switchDatabase();
        if ($request->status == 'berhasil') {

            if ($kodeBayar == 1) {
                // $sekolah = Sekolah::where('kode_sekolah', $kodeSekolah)->first();

                if (!$sekolah) {
                    return response()->json([
                        'message' => 'Sekolah tidak ditemukan'
                    ], 404);
                }

                $sekolahModel = new Sekolah();
                $this->databaseSwitcher->switchDatabase($sekolah->db, $sekolahModel);

                $studentName = Student::where('student_nis', $nis)->first();
                $newStr = explode(" ", $studentName->student_full_name);
                $secret = '39F0ADF6-7E9D-4AEB-9934-53DB0145844E';
                $firstname = $newStr[0];
                $lastname = $newStr[1];
                $email =  $this->RemoveSpecialChar($firstname) . $this->RemoveSpecialChar($lastname) . '@epesantren.co.id';
                $dataCallback = [
                    'trx_id' => $request->trx_id,
                    'sid' => $kodeSekolah,
                    'status' => $request->status,
                    'status_code' => $request->status_code,
                    'via' => $request->via,
                    'channel' => $request->channel,
                    'va' => $request->va,
                    'reference_id' => $kodeSekolah,
                    'total' => $request->total,
                    'fees' => $request->fee,
                    'comments' => 'coba', //belum fix
                    'date' => Carbon::now()->format('Y-m-d'),
                    'buyer_name' => $studentName->student_full_name, //belum fix
                    'buyer_email' => $email, //belum fix
                    'buyer_phone' => $studentName->student_parent_phone, //belum fix
                    'is_escrow' => 0, //belum fix
                    'created_date' => Carbon::now()->format('Y-m-d'),
                ];

                $dataDb = $request->reference_id;
                $parts = explode('|', $dataDb);
                $kodeSekolah = trim($parts[0]);
                $nis = trim($parts[1]);
                $kodeBayar = trim($parts[2]);

                $trx_id = $request->trx_id;
                IpaymuCallback::create($dataCallback);
                $responseStatus = IpaymuCallback::where('trx_id', $trx_id)->first();
                $status = $responseStatus->status;
                $update = IpaymuTransaksi::where('va_transactionId', $trx_id)->first();
                $student = Student::where('student_nis', $nis)->first();
                $major = $student->majors_majors_id;
                $majorId = major::where('majors_id', $major)->first();
                $majorName = $majorId->majors_short_name;
                $like = 'SP' . str_replace(" ", "", $majorName . $nis);
                $idMajors = $major;
                $noref = Kas::getNoref($like, $idMajors);
                $paymentNoref = $like . $noref;

                $noTrans = $update->id_transaksi;
                if ($update && $paymentNoref) {
                    $update->status = $request->status;
                    $update->noref = $paymentNoref;
                    $update->di_bayar = $request->total;
                    $update->save();
                } else {
                    Log::error("Transaksi dengan ID $trx_id tidak ditemukan.");
                }

                $amount = $request->total;
                $bank_sender = $request->channel;
                $bankVee = data_ipaymu_channel::where('kode', $bank_sender)->first();
                $fee = $bankVee->fee;
                $totalBayarTanpaFee = $amount - $fee;

                $bulan = Bulan::where('ipaymu_no_trans', $update->id_transaksi)->get();
                $statusIpaymu = 'LUNAS';
                $statusBulan = 1;
                $userId = $student->student_id;
                $major = $student->majors_majors_id;
                $akunIpaymu = AkunIpaymu::where('unit_id', $major)->where('tipe', 'pembayaran')->first();
                $akunId = $akunIpaymu->akun_id;
                $idTransaksiPesan = $update->id_transaksi;
                //

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


                //update pembayaran bulan
                if ($bulan->count() > 0) {
                    foreach ($bulan as $value) {
                        $value->update([
                            'bulan_account_id' => $akunId,
                            'ipaymu_status' => $statusIpaymu,
                            'bulan_noref' => $paymentNoref,
                            'bulan_status' => $statusBulan,
                            'bulan_date_pay' => $waktuAsli,
                            'user_user_id' => $userId,
                        ]);
                    }
                }

                //update table bebas_pay_mobile
                $bebasPayMobile = BebasPayMobile::where('ipaymu_no_trans', $noTrans)->get();
                $BebasNoRef = $paymentNoref;

                foreach ($bebasPayMobile as $value) {
                    $value->update([
                        'bebas_pay_noref' => $BebasNoRef,
                        'bebas_pay_account_id' => $akunId,
                        'bebas_pay_last_update' => now($waktu),
                        'ipaymu_status' => 'LUNAS'
                    ]);
                }

                //insert ke bebas_pay berdasarkan id transaksi
                $bebasPay = BebasPayMobile::where('ipaymu_no_trans', $noTrans)->get();
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
                            'bebas_pay_input_date' => $waktuAsli,
                            'ipaymu_no_trans' => $value->ipaymu_no_trans,
                            'ipaymu_status' => $value->ipaymu_status,
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
                $va = IpaymuTransaksi::where('id_transaksi', $noTrans)->first();
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
                $bulanLogTrx = Bulan::where('ipaymu_no_trans', $noTrans)->get();
                $bebasLogTrx = BebasPay::where('ipaymu_no_trans', $noTrans)->get();

                $logTrxData = [];



                // Proses data dari bulanLogTrx
                foreach ($bulanLogTrx as $bulan) {
                    $logTrxData[] = [
                        'student_student_id' => $student->student_id,
                        'bulan_bulan_id' => $bulan->bulan_id,
                        'bebas_pay_bebas_pay_id' => null, // Tidak diisi
                        'sekolah_id' => $kodeSekolah,
                        'log_trx_input_date' => now($waktu),
                        'log_trx_last_update' => now($waktu),
                    ];
                }

                foreach ($bebasLogTrx as $bebas) {
                    $logTrxData[] = [
                        'student_student_id' => $student->student_id,
                        'bulan_bulan_id' => null, // Tidak diisi
                        'bebas_pay_bebas_pay_id' => $bebas->bebas_bebas_id,
                        'sekolah_id' => $kodeSekolah,
                        'log_trx_input_date' => now($waktu),
                        'log_trx_last_update' => now($waktu),
                    ];
                }

                // Masukkan semua data dalam satu proses insert
                LogTrx::insert($logTrxData);

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

                $dataJurnalUmum =  [
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

                //  dd($accountCode, $bebasAccountCode);
                if (($accountCode && $accountCode->account_code) || ($bebasAccountCode && $bebasAccountCode->account_code)) {
                    $JurnalUmumDetailDebit = [
                        'id_jurnal' => $idJurnal,
                        'account_code' => $accountCode->account_code ?? $bebasAccountCode->account_code ?? null,
                        'debet' => $totalBayarTanpaFee,
                        'kredit' => 0.00
                    ];

                    //  dd($JurnalUmumDetailDebit);
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
                    // $accountId = Bulan::where('bulan_noref', $paymentNoref)->first();
                    // $accountCode = Account::where('account_id', $accountId->bulan_account_id)->first();

                    // $accountCodeKredit = Bulan::where('bulan_noref', $paymentNoref)->get();
                    // $paymentIds = $accountCodeKredit->pluck('payment_payment_id');
                    // $bulanIds = $accountCodeKredit->pluck('bulan_id');
                    // $payment = Payment::whereIn('payment_id', $paymentIds)->get();

                    // //     // Loop untuk mengambil 'pos_pos_id' dari setiap elemen dalam array
                    // $posPosIds = $payment->map(function ($paymentItem) {
                    //     return $paymentItem->pos_pos_id;
                    // });

                    // //     // nilai pos
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
                    // //dd($getPayKredit);
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

                    //     JurnalUmumDetail::insert($jurnalUmumDetailKreditbulan->toArray()); //Tampilkan hasil
                    //     //dd($jurnalUmumDetailKreditbulan);
                    // }
                }

                if ($cekBebas->isNotEmpty()) {
                    // Mencari bebas account_id (hasilnya array integer)
                    $bebasPayAccountIds = BebasPay::where('bebas_pay_noref', $paymentNoref)->pluck('bebas_pay_id')->toArray();

                    $bebasId = BebasPay::whereIn('bebas_pay_id', $bebasPayAccountIds)->pluck('bebas_bebas_id')->toArray();

                    $payment = Bebas::whereIn('bebas_id', $bebasId)->pluck('payment_payment_id')->toArray();

                    $pos = Payment::whereIn('payment_id', $payment)->pluck('pos_pos_id')->toArray();

                    $account = Pos::whereIn('pos_id', $pos)->pluck('account_account_id')->toArray();

                    $accountCodes1 = Account::whereIn('account_id', $account)->pluck('account_code')->toArray();

                    $bebasBills = BebasPay::whereIn('bebas_pay_id', $bebasPayAccountIds)->get();
                    $getBebasBill = $bebasBills->pluck('bebas_pay_bill', 'bebas_pay_id')->toArray();

                    $getBebasBillCollection = collect($getBebasBill);
                    $accountCodesCollection = collect($accountCodes1);

                    // Array untuk menyimpan data yang akan diinsert
                    $jurnalUmumDetailBebasData = [];

                    $getBebasBillCollection->zip($accountCodesCollection)->each(function ($pair) use ($idJurnal, &$jurnalUmumDetailBebasData) {
                        [$item, $accountCode] = $pair;
                        $jurnalUmumDetailBebasData[] = [
                            'id_jurnal' => $idJurnal,
                            'account_code' => $accountCode,
                            'debet' => 0.00,
                            'kredit' => $item,
                        ];
                    });
                    //  dd($jurnalUmumDetailBebasData);
                    // Insert semua data sekaligus
                    JurnalUmumDetail::insert($jurnalUmumDetailBebasData);
                }

                //JurnalUmumDetail::insert($jurnalUmumDetailBebas);

                // $jurnalUmumDetailBebas = [];
                // foreach ($accountCodes as $index => $accountCode) {
                //     $jurnalUmumDetailBebas[] = [
                //         'id_jurnal' => $idJurnal,
                //         'account_code' => $accountCode,
                //         'debet' => 0.00,
                //         'kredit' => $getBebasBill[$bebasPayAccountIds[$index]] ?? 0.00, // Cek data terkait
                //     ];
                // }

                // Insert ke tabel JurnalUmumDetail
                return response()->json([
                    'trx_id' => $request->trx_id,
                    'status' => $request->status,
                    'status_code' => $request->status_code,
                    'via' => $request->via,
                    'channel' => $request->channel,
                    'va' => $request->va,
                    'reference_id' => $request->reference_id,
                    'total' => $request->total,
                    'fees' => $request->fee,
                    'date' => Carbon::now()->format('Y-m-d'),
                    'data' => $request->reference_id,
                    'kode_sekolah' => $kodeSekolah,
                    'nis' => $nis,
                    'kode_bayar' => $kodeBayar,
                    'idJurnal' => $idJurnal,
                    // 'getPayKredit' => $getPayKredit,
                    //'cek_bulan' => $cekBulan, tampil 2
                    // 'bebas_pay_account_id' => $bebasId1->bebas_pay_account_id,
                    // 'bebas_account_code' => $bebasAccountCode->account_code,
                    // 'total_bayar_tanpa_fee' => $totalBayarTanpaFee,
                ]);
            } elseif ($kodeBayar == 2) {

                $dataDb = $request->reference_id;
                $parts = explode('|', $dataDb);
                $catatan = trim($parts[4]);
                $studentName = Student::where('student_nis', $nis)->first();
                $newStr = explode(" ", $studentName->student_full_name);

                $firstname = $newStr[0];
                $lastname = $newStr[1];
                $email =  $this->RemoveSpecialChar($firstname) . $this->RemoveSpecialChar($lastname) . '@epesantren.co.id';
                $dataCallbackTabungan = [
                    'trx_id' => $request->trx_id,
                    'sid' => $kodeSekolah,
                    'status' => $request->status,
                    'status_code' => $request->status_code,
                    'via' => $request->via,
                    'channel' => $request->channel,
                    'va' => $request->va,
                    'reference_id' => $kodeSekolah,
                    'total' => $request->total,
                    'fees' => $request->fee,
                    'comments' => 'coba', //belum fix
                    'date' => Carbon::now()->format('Y-m-d'),
                    'buyer_name' => $studentName->student_full_name, //belum fix
                    'buyer_email' => $email, //belum fix
                    'buyer_phone' => $studentName->student_parent_phone, //belum fix
                    'is_escrow' => 0, //belum fix
                    'created_date' => Carbon::now()->format('Y-m-d'),
                ];
                $trx_id = $request->trx_id;
                IpaymuCallbackTabungan::create($dataCallbackTabungan);
                $responseStatus = IpaymuCallbackTabungan::where('trx_id', $trx_id)->first();
                $status = $responseStatus->status;
                $update = IpaymuTransaksiTabungan::where('va_transactionId', $trx_id)->first();
                $student = Student::where('student_nis', $nis)->first();

                $waktu = $student->waktu_indonesia;

                if ($update) {
                    $update->status = $status;
                    $update->di_bayar = $request->total;
                    $update->save();
                }

                $period = Period::where('period_status', 1)->first();
                $bankVee = data_ipaymu_channel::where('kode', $request->channel)->first();
                $fee = $bankVee->fee;
                $totalBayarTanpaFee = $request->total - $fee;

                //insert ke data banking
                $dataBanking = [
                    'banking_period_id' => $period->period_id,
                    //'banking_debit' => $decoded_data['amount'],
                    'banking_debit' => $totalBayarTanpaFee,
                    'banking_kredit' => 0,
                    'banking_date' => now($waktu),
                    'banking_code' => 1,
                    'banking_student_id' => $student->student_id,
                    'banking_note' => $catatan,
                    'user_user_id' => $student->student_id,
                ];

                Banking::create($dataBanking);

                $infoAppTab = [
                    'student_id' => $student->student_id,
                    'info' => 'Pembayaran top up tabungan atas nama ' . $student->student_full_name . ' dengan nominal ' . $totalBayarTanpaFee,
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

                $accountCode = AkunIpayMu::where('unit_id', $student->majors_majors_id)->where('tipe', 'tabungan')->first();
                $getAccountCode = $accountCode->akun_id;

                $kodeAkun = Account::where('account_id', $getAccountCode)->first();
                $getKodeAkun = $kodeAkun->account_code;

                $debitTabungan = [
                    'id_jurnal' => $idJurnal1,
                    'account_code' => $getKodeAkun,
                    'debet' => $totalBayarTanpaFee,
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

                $kreditTabungan = [
                    'id_jurnal' => $idJurnal1,
                    'account_code' => $akunKreditCode,
                    'debet' => 0.00,
                    'kredit' => $totalBayarTanpaFee
                ];
                JurnalUmumDetail::create($kreditTabungan);

                return response()->json([
                    'trx_id' => $request->trx_id,
                    'status' => $request->status,
                    'status_code' => $request->status_code,
                    'via' => $request->via,
                    'channel' => $request->channel,
                    'va' => $request->va,
                    'reference_id' => $request->reference_id,
                    'total' => $request->total,
                    'fees' => $request->fee,
                    'date' => Carbon::now()->format('Y-m-d'),
                    'data' => $request->reference_id,
                    'kode_sekolah' => $kodeSekolah,
                    'nis' => $nis,
                    'kode_bayar' => $kodeBayar,
                    'parts' => $parts,
                    'catatan' => $catatan,
                    'accountCode' => $getKodeAkun,
                ]);
            } elseif ($kodeBayar == 3) {
                return response()->json([
                    'trx_id' => $request->trx_id,
                    'status' => $request->status,
                    'status_code' => $request->status_code,
                    'via' => $request->via,
                    'channel' => $request->channel,
                    'va' => $request->va,
                    'reference_id' => $request->reference_id,
                    'total' => $request->total,
                    'fees' => $request->fee,
                    'date' => Carbon::now()->format('Y-m-d'),
                    'data' => $request->reference_id,
                    'kode_sekolah' => $kodeSekolah,
                    'nis' => $nis,
                    'kode_bayar' => $kodeBayar,
                    // 'id_program_donasi' => $kodeProgram
                ]);
            }
        } else {
            if ($kodeBayar == 1) {

                if (!$sekolah) {
                    return response()->json([
                        'message' => 'Sekolah tidak ditemukan'
                    ], 404);
                }

                $sekolahModel = new Sekolah();
                $this->databaseSwitcher->switchDatabase($sekolah->db, $sekolahModel);

                $studentName = Student::where('student_nis', $nis)->first();
                $newStr = explode(" ", $studentName->student_full_name);
                $secret = '39F0ADF6-7E9D-4AEB-9934-53DB0145844E';
                $firstname = $newStr[0];
                $lastname = $newStr[1];
                $email =  $this->RemoveSpecialChar($firstname) . $this->RemoveSpecialChar($lastname) . '@epesantren.co.id';
                $dataCallback = [
                    'trx_id' => $request->trx_id,
                    'sid' => $kodeSekolah,
                    'status' => $request->status,
                    'status_code' => $request->status_code,
                    'via' => $request->via,
                    'channel' => $request->channel,
                    'va' => $request->va,
                    'reference_id' => $kodeSekolah,
                    'total' => $request->total,
                    'fees' => $request->fee,
                    'comments' => 'coba', //belum fix
                    'date' => Carbon::now()->format('Y-m-d'),
                    'buyer_name' => $studentName->student_full_name, //belum fix
                    'buyer_email' => $email, //belum fix
                    'buyer_phone' => $studentName->student_parent_phone, //belum fix
                    'is_escrow' => 0, //belum fix
                    'created_date' => Carbon::now()->format('Y-m-d'),
                ];

                $dataDb = $request->reference_id;
                $parts = explode('|', $dataDb);
                $kodeSekolah = trim($parts[0]);
                $nis = trim($parts[1]);
                $kodeBayar = trim($parts[2]);

                $trx_id = $request->trx_id;
                IpaymuCallback::create($dataCallback);
                $responseStatus = IpaymuCallback::where('trx_id', $trx_id)->first();
                $status = $responseStatus->status;
                $update = IpaymuTransaksi::where('va_transactionId', $trx_id)->first();
                $student = Student::where('student_nis', $nis)->first();

                //update status pembayaran
                if ($update) {
                    $update->status = $status;
                    $update->di_bayar = 0;
                    $update->save();
                }

                //masuk ke info app
                $infoApp = [
                    'student_id' => $student->student_id,
                    'info' => 'Pembayaran atas nama ' . $student->student_full_name . ' dengan no va ' . $request->va . ' sebesar ' . $request->total . 'status pembayaran :' . $request->status,
                    'created_at' => Carbon::now()->format('Y-m-d')
                ];

                InfoApp::create($infoApp);
                $totalBayarFormatted = 'Rp. ' . number_format($request->total, 0, ',', '.');
                $tanggal = Carbon::now()->format('Y-m-d');

                $pesan = <<<EOT
                Bagian Administrasi  {$sekolah->nama_sekolah}

                Assalamualaikum warahmatullahi wabarakatuh,

                Tanggal: {$tanggal}

                Yth. Ayah/Bunda dari ananda {$student->student_full_name},

                Terima kasih telah menggunakan aplikasi ePesantren. Berikut detail pembayaran yang gagal:

                Total                 : {$totalBayarFormatted}
                Nomor VA        : {$request->va}
                Bank                 : {$request->channel}



                Terimakah telah melakukan pembayaran.

                Hormat kami,
                Bagian Administrasi
                EOT;

                // notif wa pembayaran
                $whatsappService = new WhatsappServicePembayaran();
                $nowa = $student->student_parent_phone;
                $whatsappService->kirimPesan($nowa, $pesan);
                return response()->json([
                    'message' => 'Pembayaran gagal'
                ]);
            } elseif ($kodeBayar == 2) {
                $dataDb = $request->reference_id;
                $parts = explode('|', $dataDb);
                $catatan = trim($parts[4]);
                $studentName = Student::where('student_nis', $nis)->first();
                $newStr = explode(" ", $studentName->student_full_name);

                $firstname = $newStr[0];
                $lastname = $newStr[1];
                $email =  $this->RemoveSpecialChar($firstname) . $this->RemoveSpecialChar($lastname) . '@epesantren.co.id';
                $dataCallbackTabungan = [
                    'trx_id' => $request->trx_id,
                    'sid' => $kodeSekolah,
                    'status' => $request->status,
                    'status_code' => $request->status_code,
                    'via' => $request->via,
                    'channel' => $request->channel,
                    'va' => $request->va,
                    'reference_id' => $kodeSekolah,
                    'total' => $request->total,
                    'fees' => $request->fee,
                    'comments' => 'coba', //belum fix
                    'date' => Carbon::now()->format('Y-m-d'),
                    'buyer_name' => $studentName->student_full_name, //belum fix
                    'buyer_email' => $email, //belum fix
                    'buyer_phone' => $studentName->student_parent_phone, //belum fix
                    'is_escrow' => 0, //belum fix
                    'created_date' => Carbon::now()->format('Y-m-d'),
                ];
                $trx_id = $request->trx_id;
                IpaymuCallbackTabungan::create($dataCallbackTabungan);
                $responseStatus = IpaymuCallbackTabungan::where('trx_id', $trx_id)->first();
                $status = $responseStatus->status;
                $update = IpaymuTransaksiTabungan::where('va_transactionId', $trx_id)->first();
                $student = Student::where('student_nis', $nis)->first();

                $waktu = $student->waktu_indonesia;

                if ($update) {
                    $update->status = $status;
                    $update->di_bayar = 0;
                    $update->save();
                }

                $bankVee = data_ipaymu_channel::where('kode', $request->channel)->first();
                $fee = $bankVee->fee;
                $totalBayarTanpaFee = $request->total - $fee;

                //masuk info app
                $infoApp = [
                    'student_id' => $student->student_id,
                    'info' => 'Top up Tabungan gagal sebesar ' . $totalBayarTanpaFee . ' atas nama ' . $student->student_full_name,
                    'created_at' => Carbon::now()->format('Y-m-d')
                ];
                InfoApp::create($infoApp);
                //notif wa gagal pembayaran
                $nama_sekolah = $sekolah->nama_sekolah;
                $noVa = $request->va;
                $bank = $request->channel;

                $tanggal = Carbon::now()->format('Y-m-d');
                $nama = $student->student_full_name;
                $totalBayarFormatted = 'Rp. ' . number_format($request->total, 0, ',', '.');

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

                return response()->json([
                    'trx_id' => $request->trx_id,
                    'status' => $request->status,
                    'status_code' => $request->status_code,
                    'via' => $request->via,
                    'channel' => $request->channel,
                    'va' => $request->va,
                    'reference_id' => $request->reference_id,
                    'total' => $request->total,
                    'fees' => $request->fee,
                    'date' => Carbon::now()->format('Y-m-d'),
                    'data' => $request->reference_id,
                    'kode_sekolah' => $kodeSekolah,
                    'nis' => $nis,
                    'kode_bayar' => $kodeBayar,
                    'parts' => $parts,
                    'catatan' => $catatan,
                ]);
            }
        }
    }
}
