<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Kas;
use App\Models\Pos;
use App\Models\Bebas;
use App\Models\BebasPay;
use App\Models\major;
use App\Models\Letter;
use App\Models\Period;
use App\Models\Payment;
use Illuminate\Http\Request;
use App\Models\BebasPayMobile;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Validator;

class PembayaranBebasController extends Controller
{
    public function index(Request $reqest)
    {
        $user = auth()->user();
        $periodId = $reqest->query('period_id');
        $setting = Setting::where('setting_name', 'api_secret_key')->first();
        // dd($setting);
        if ($setting) {

            $data = Bebas::with(['payment', 'payment.pos'])
                ->where('student_student_id', $user->student_id)
                ->where('bebas_bill', '!=', DB::raw('bebas_total_pay'));
            //    dd($data);
            if ($periodId) {
                $data->whereHas('payment', function ($query) use ($periodId) {
                    $query->where('period_period_id', $periodId);
                });
            }
            //->get()
            $data = $data->get()->map(function ($item) {
                $bebas_bill = $item->bebas_bill;
                $bebas_total_pay = $item->bebas_total_pay;
                $sisa = $bebas_bill - $bebas_total_pay;
                $payment = $item->payment;
                $posName = $payment->pos;

                $tahun = Period::where('period_id', $payment->period_period_id)->first();
                $pembayaran = BebasPayMobile::where('bebas_bebas_id', $item->bebas_id)
                    //->where('bebas_pay_last_update', null)
                    ->where('flip_status', 'Ready')
                    ->first();
                //  $bebas_pay_bill = $pembayaran->bebas_pay_bill;
                $bebas_pay_bill = $pembayaran ? $pembayaran->bebas_pay_bill : null;
                return [
                    'bebas_id' => $item->bebas_id,
                    'bebas' => $posName->pos_name . ' ' . $tahun->period_start . '/' . $tahun->period_end,
                    'bebas_bill' => (int)$item->bebas_bill,
                    'bebas_total_pay' => (int) $item->bebas_total_pay,
                    'bebas_diskon' => (int)$item->bebas_diskon,
                    'period' =>  $payment->period_period_id,
                    'sisa' => $sisa,
                    //  'jumlah' => $sisa,
                    'mau_bayar' => (int) $bebas_pay_bill,
                    'is_in_cart' => ((int) $bebas_pay_bill) == 0 ? false : true,
                    'status_pembayaran' => $bebas_pay_bill ? true : false,
                ];
            });


            return response()->json([
                'is_correct' => true,
                'message' => 'success',
                'bebas' => $data
            ]);
        } else {
            $data = Bebas::with(['payment', 'payment.pos'])
                ->where('student_student_id', $user->student_id)
                ->where('bebas_bill', '!=', DB::raw('bebas_total_pay'));
            if ($periodId) {
                $data->whereHas('payment', function ($query) use ($periodId) {
                    $query->where('period_period_id', $periodId);
                });
            }
            //->get()
            $data = $data->get()->map(function ($item) {
                $bebas_bill = $item->bebas_bill;
                $bebas_total_pay = $item->bebas_total_pay;
                $sisa = $bebas_bill - $bebas_total_pay;
                $payment = $item->payment;
                $posName = $payment->pos;

                $tahun = Period::where('period_id', $payment->period_period_id)->first();
                $pembayaran = BebasPayMobile::where('bebas_bebas_id', $item->bebas_id)
                    //->where('bebas_pay_last_update', null)
                    ->where('ipaymu_status', 'Ready')
                    ->first();
                //  $bebas_pay_bill = $pembayaran->bebas_pay_bill;
                $bebas_pay_bill = $pembayaran ? $pembayaran->bebas_pay_bill : null;
                return [
                    'bebas_id' => $item->bebas_id,
                    'bebas' => $posName->pos_name . ' ' . $tahun->period_start . '/' . $tahun->period_end,
                    'bebas_bill' => (int)$item->bebas_bill,
                    'bebas_total_pay' => (int) $item->bebas_total_pay,
                    'bebas_diskon' => (int)$item->bebas_diskon,
                    'period' =>  $payment->period_period_id,
                    'sisa' => $sisa,
                    'jumlah' => $sisa,
                    'mau_bayar' => (int) $bebas_pay_bill,
                    'is_in_cart' => ((int) $bebas_pay_bill) == 0 ? false : true,
                    'status_pembayaran' => $bebas_pay_bill ? true : false,
                ];
            });


            return response()->json([
                'is_correct' => true,
                'message' => 'success',
                'detail' => $data
            ]);
        }
    }

    public function lunas(Request $request)
    {
        $user = auth()->user();
        $periodId = $request->query('period_id');
        $setting = Setting::where('setting_name', 'api_secret_key')->first();
        if ($setting) {
            $data = Bebas::with(['payment', 'payment.pos', 'payment.period'])
                ->where('student_student_id', $user->student_id)
                ->where('bebas_bill', '=', DB::raw('bebas_total_pay'));
            if ($periodId) {
                $data->whereHas('payment', function ($query) use ($periodId) {
                    $query->where('period_period_id', $periodId);
                });
            }

            //->get()
            $data = $data->get()->map(function ($item) {
                $bebas_bill = $item->bebas_bill;
                $bebas_total_pay = $item->bebas_total_pay;
                $sisa = $bebas_bill - $bebas_total_pay;
                $payment = $item->payment;
                $posName = $payment->pos;

                $periodStart = $payment->period ? $payment->period->period_start : null;
                $periodEnd = $payment->period ? $payment->period->period_end : null;
                $pembayaran = BebasPayMobile::where('bebas_bebas_id', $item->bebas_id)
                    //->where('bebas_pay_last_update', null)
                    ->where('flip_status', 'READY')
                    ->first();
                //  $bebas_pay_bill = $pembayaran->bebas_pay_bill;
                $bebas_pay_bill = $pembayaran ? $pembayaran->bebas_pay_bill : null;
                return [
                    'bebas_id' => $item->bebas_id,
                    'bebas' => $posName->pos_name,
                    'bebas_bill' => (int)$item->bebas_bill,
                    'bebas_total_pay' => (int) $item->bebas_total_pay,
                    'bebas_diskon' => (int)$item->bebas_diskon,
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'period' =>  $payment->period_period_id,
                    'status' => 'LUNAS',
                    'sisa' => $sisa,
                    'jumlah' => $sisa,
                    'mau_bayar' => (int) $bebas_pay_bill,
                    'is_in_cart' => ((int) $bebas_pay_bill) == 0 ? false : true,
                    'status_pembayaran' => $bebas_pay_bill ? true : false,
                ];
            });


            return response()->json([
                'is_correct' => true,
                'message' => 'success',
                'bebas_lunas' => $data
            ]);
        } else {
            $data = Bebas::with(['payment', 'payment.pos', 'payment.period'])
                ->where('student_student_id', $user->student_id)
                ->where('bebas_bill', '=', DB::raw('bebas_total_pay'));
            if ($periodId) {
                $data->whereHas('payment', function ($query) use ($periodId) {
                    $query->where('period_period_id', $periodId);
                });
            }
            //dd($data);
            //->get()
            $data = $data->get()->map(function ($item) {
                $bebas_bill = $item->bebas_bill;
                $bebas_total_pay = $item->bebas_total_pay;
                $sisa = $bebas_bill - $bebas_total_pay;
                $payment = $item->payment;
                $posName = $payment->pos;

                $periodStart = $payment->period ? $payment->period->period_start : null;
                $periodEnd = $payment->period ? $payment->period->period_end : null;
                $pembayaran = BebasPayMobile::where('bebas_bebas_id', $item->bebas_id)
                    //->where('bebas_pay_last_update', null)
                    ->where('ipaymu_status', 'READY')
                    ->first();
                //  $bebas_pay_bill = $pembayaran->bebas_pay_bill;
                $bebas_pay_bill = $pembayaran ? $pembayaran->bebas_pay_bill : null;
                return [
                    'bebas_id' => $item->bebas_id,
                    'bebas' => $posName->pos_name,
                    'bebas_bill' => (int)$item->bebas_bill,
                    'bebas_total_pay' => (int) $item->bebas_total_pay,
                    'bebas_diskon' => (int)$item->bebas_diskon,
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'period' =>  $payment->period_period_id,
                    'status' => 'LUNAS',
                    'sisa' => $sisa,
                    'jumlah' => $sisa,
                    'mau_bayar' => (int) $bebas_pay_bill,
                    'is_in_cart' => ((int) $bebas_pay_bill) == 0 ? false : true,
                    'status_pembayaran' => $bebas_pay_bill ? true : false,
                ];
            });


            return response()->json([
                'is_correct' => true,
                'message' => 'success',
                'detail' => $data
            ]);
        }
    }

    public function cicilan()
    {
        $user = auth()->user();

        $cicilan = BebasPay::where('user_user_id', $user->student_id)
            ->get()
            ->map(function ($item) {
                return [
                    'bebas_bebas_id' => $item->bebas_bebas_id,
                    'nominal' => (int) $item->bebas_pay_bill,
                    'bebas_pay_input_date' => $item->bebas_pay_input_date,
                ];
            })
            ->groupBy('bebas_bebas_id'); // Mengelompokkan berdasarkan bebas_bebas_id


        $result = $cicilan->map(function ($group, $bebasId) {
            $totalLunas = Bebas::where('bebas_id', $bebasId)->value('bebas_bill');

            //untuk mendapatkan nama makan bebas_id dilihat payment_payment_id nya lalu ke table payment lalu
            //ke field pos_pos_id lalu ketable pos dan ambil pos name

            $paymentPaymentId = Bebas::where('bebas_id', $bebasId)->value('payment_payment_id');

            // Ambil pos_pos_id dari tabel Payment
            $posPosId = Payment::where('payment_id', $paymentPaymentId)->value('pos_pos_id');

            $periodId = Payment::where('payment_id', $paymentPaymentId)->value('period_period_id');

            // Ambil pos_name dari tabel Pos
            $nama = Pos::where('pos_id', $posPosId)->value('pos_name');

            $period1 = Period::where('period_id', $periodId)->value('period_start');
            $period2 = Period::where('period_id', $periodId)->value('period_end');
            // $startYear = $period->period_start ?? null;
            // $endYear = $period->period_end ?? null;

            $totalNominal = $group->sum('nominal'); // Menghitung total nominal

            // Tentukan nilai kekurangan dan status berdasarkan logika
            $kekurangan = $totalLunas - $totalNominal;
            $status = $kekurangan > 0 ? 'Belum Lunas' : 'Lunas';

            return [
                'bebas_bebas_id' => $bebasId,
                //'nama' => $nama  . ' ' . $period1 . '/' . $period2, // Ubah ini sesuai kebutuhan
                'nama' => trim(($nama ? $nama : '') . (($period1 || $period2) ? ' ' . $period1 . '/' . $period2 : '')),
                'kekurangan' => $kekurangan,
                'status' => $status,
                'total_lunas' => (int) $totalLunas,
                'total' => $totalNominal,
                'cicilan' => $group
            ];
        })->values();

        return response()->json([
            'is_correct' => true,
            'bebas_history' => $result
        ]);
    }


    public function store(Request $request)
    {
        $user = auth()->user();
        $majors = major::where('majors_id', $user->majors_majors_id)->first();
        //claim payload
        $token = JWTAuth::parseToken();

        // Get the token payload
        $claims = $token->getPayload();

        $kode_sekolah = $claims->get('kode_sekolah');
        $setting = Setting::where('setting_name', 'api_secret_key')->first();
        //  dd($setting);
        if ($setting) {
            $validator = Validator::make($request->all(), [
                'bebas_bebas_id' => 'required',
                'bebas_pay_noref' => 'nullable',
                'bebas_pay_account_id' => 'nullable',
                'bebas_pay_number' => 'nullable',
                'bebas_pay_bill' => 'required',
                'bebas_pay_desc' => 'nullable',
                'user_user_id' => 'nullable',
                'sekolah_id' => 'nullable',
                'bebas_pay_input_date' => 'nullable',
                'bebas_pay_last_update' => 'nullable',
                'ipaymu_no_trans' => 'nullable',
                'ipaymu_status' => 'nullable',
                'flip_no_trans' => 'nullable',
                'flip_status' => 'nullable',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'is_correct' => false,
                    'message' => $validator->erros()
                ], 400);
            }
            //membuat string
            $like = 'SP' . str_replace(" ", "", $majors->majors_short_name . $user->student_nis);
            $idMajors = $user->majors_majors_id;
            $noref = Kas::getNoref($like, $idMajors);
            $paymentNoref = $like . $noref;

            //tanggal
            $tanggal = Carbon::now()->format('Y-m-d');

            //bebas pay number
            $tahun = Carbon::now()->format('Y');
            $bulan = Carbon::now()->format('m');
            // $tanggal = Carbon::now()->format('d');
            $lastLetter = Letter::orderBy('letter_id', 'desc')->first();

            // Jika surat terakhir tahun ini, lanjutkan nomor urutnya
            if ($lastLetter && $lastLetter->letter_year == Carbon::now()->year) {
                // Nomor surat baru
                $newNumber = sprintf('%05d', $lastLetter->letter_number + 1);
            } else {
                // Jika tahun surat terakhir berbeda atau tidak ada surat sama sekali, mulai dari nomor 00001
                $newNumber = '00001';
            }
            $bebasPayNumber = $tahun . $bulan  . $newNumber;
            $dataUntukDikirim = [
                'bebas_bebas_id' => $request->bebas_bebas_id,
                'bebas_pay_noref' => $paymentNoref,
                'bebas_pay_noref' => '',
                'bebas_pay_account_id' => 1,
                'bebas_pay_number' => $bebasPayNumber,
                'bebas_pay_bill' => $request->bebas_pay_bill,
                'bebas_pay_desc' => 'membayar',
                'user_user_id' => $user->student_id,
                'sekolah_id' => $kode_sekolah,
                'bebas_pay_input_date' => $tanggal,
                'bebas_pay_last_upate' => null,
                'ipaymu_no_trans' => null,
                'ipaymu_status' => null,
                'flip_no_trans' => null,
                'flip_status' => 'READY'
            ];
            // dd($dataUntukDikirim);
            BebasPayMobile::create($dataUntukDikirim);


            return response()->json([
                'is_correct' => true,
                'message' => 'cicilan berhasil di tambahkan'
            ], 200);
        } else {
            $validator = Validator::make($request->all(), [
                'bebas_bebas_id' => 'required',
                'bebas_pay_noref' => 'nullable',
                'bebas_pay_account_id' => 'nullable',
                'bebas_pay_number' => 'nullable',
                'bebas_pay_bill' => 'required',
                'bebas_pay_desc' => 'nullable',
                'user_user_id' => 'nullable',
                'sekolah_id' => 'nullable',
                'bebas_pay_input_date' => 'nullable',
                'bebas_pay_last_update' => 'nullable',
                'ipaymu_no_trans' => 'nullable',
                'ipaymu_status' => 'nullable',
                'flip_no_trans' => 'nullable',
                'flip_status' => 'nullable',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'is_correct' => false,
                    'message' => $validator->erros()
                ], 400);
            }
            //membuat string
            $like = 'SP' . str_replace(" ", "", $majors->majors_short_name . $user->student_nis);
            $idMajors = $user->majors_majors_id;
            $noref = Kas::getNoref($like, $idMajors);
            $paymentNoref = $like . $noref;

            //tanggal
            $tanggal = Carbon::now()->format('Y-m-d');

            //bebas pay number
            $tahun = Carbon::now()->format('Y');
            $bulan = Carbon::now()->format('m');
            // $tanggal = Carbon::now()->format('d');
            $lastLetter = Letter::orderBy('letter_id', 'desc')->first();

            // Jika surat terakhir tahun ini, lanjutkan nomor urutnya
            if ($lastLetter && $lastLetter->letter_year == Carbon::now()->year) {
                // Nomor surat baru
                $newNumber = sprintf('%05d', $lastLetter->letter_number + 1);
            } else {
                // Jika tahun surat terakhir berbeda atau tidak ada surat sama sekali, mulai dari nomor 00001
                $newNumber = '00001';
            }
            $bebasPayNumber = $tahun . $bulan  . $newNumber;
            $dataUntukDikirim = [
                'bebas_bebas_id' => $request->bebas_bebas_id,
                'bebas_pay_noref' => $paymentNoref,
                'bebas_pay_noref' => '',
                'bebas_pay_account_id' => 1,
                'bebas_pay_number' => $bebasPayNumber,
                'bebas_pay_bill' => $request->bebas_pay_bill,
                'bebas_pay_desc' => 'membayar',
                'user_user_id' => $user->student_id,
                'sekolah_id' => $kode_sekolah,
                'bebas_pay_input_date' => $tanggal,
                'bebas_pay_last_upate' => null,
                'ipaymu_no_trans' => null,
                'ipaymu_status' => 'READY',
                'flip_no_trans' => null,
                'flip_status' => null
            ];
            // dd($dataUntukDikirim);
            BebasPayMobile::create($dataUntukDikirim);


            return response()->json([
                'is_correct' => true,
                'message' => 'cicilan berhasil di tambahkan'
            ], 200);
        }
    }

    public function destroy($id)
    {
        $setting = Setting::where('setting_name', 'api_secret_key')->first();
        if ($setting) {
            $data = BebasPayMobile::where('bebas_bebas_id', $id)->where('flip_status', '!=', 'LUNAS')->first();
            if ($data) {
                $data->delete();
                return response()->json([
                    'is_correct' => true,
                    'message' => 'success'
                ], 200);
            } else {
                return response()->json([
                    'is_correct' => false,
                    'message' => 'data not found'
                ]);
            }
        } else {
            $data = BebasPayMobile::where('bebas_bebas_id', $id)->where('ipaymu_status', '!=', 'LUNAS')->first();
            if ($data) {
                $data->delete();
                return response()->json([
                    'is_correct' => true,
                    'message' => 'success'
                ], 200);
            } else {
                return response()->json([
                    'is_correct' => false,
                    'message' => 'data not found'
                ]);
            }
        }
    }

    public function update(Request $request, $id)
    {
        // $bebas = BebasPayMobile::where('bebas_pay_id', $id)->first();
        $bebas = BebasPayMobile::where('bebas_bebas_id', $id)->first();
        if ($bebas) {
            $newStatus = $bebas->flip_status === 'Ready' ? null : 'Ready';
            //     dd($newStatus);
            $bebas->update(['flip_status' => $newStatus]);
            // dd($bebas);
            return response()->json([
                'is_correct' => true,
                'message' => 'success'
            ], 200);
        } else {
            return response()->json([
                'is_correct' => false,
                'message' => 'data not found'
            ], 400);
        }
    }
}
