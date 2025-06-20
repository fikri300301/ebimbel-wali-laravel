<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Pos;
use App\Models\Bebas;
use App\Models\Bulan;
use App\Models\Payment;
use App\Models\Setting;
use Illuminate\Http\Request;
use App\Models\FlipTransaksi;
use App\Models\BebasPayMobile;
use App\Models\IpaymuTransaksi;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class RingkasanPemabayaranController extends Controller
{
    public function index(Request $request)
    {
        $setting = Setting::where('setting_name', 'api_secret_key')->first();
        if ($setting) {
            $user = auth()->user();
            $result = Bulan::join('batch_item as bi', 'bi.batch_item_payment_id', '=', 'bulan.payment_payment_id')
                ->join('batchpayment as bp', 'bp.id', '=', 'bi.batch_item_batchpayment_id')
                ->join('month as m', 'm.month_id', '=', 'bulan.month_month_id')
                ->join('payment as p', 'p.payment_id', '=', 'bulan.payment_payment_id') // Tambahkan join ke tabel payment
                ->select(
                    'bp.name as nama_paket',
                    'bp.id as id_paket',
                    'bulan.bulan_id',
                    'm.month_id',
                    'm.month_name',
                    'p.period_period_id',
                    DB::raw('SUM(bulan.bulan_bill) as total_bulan')
                )
                ->where('bulan.student_student_id', $user->student_id)
                ->where('bulan.bulan_status', 0)
                ->where('bulan.flip_status', 'READY')
                ->where('p.payment_is_batch', '1') // Tambahkan kondisi payment_is_batch
                ->orderBy('m.month_id', 'ASC');


            $result = $result
                ->groupBy('bp.name', 'bp.id', 'bulan.bulan_id', 'm.month_name', 'm.month_id', 'bulan.flip_status', 'p.period_period_id',)
                ->get();
            // dd($result);
            $groupedResult = $result->groupBy('id_paket')->flatMap(function ($paket) {
                $packetName = $paket->first()->nama_paket;


                $detail = $paket->groupBy('month_name')->map(function ($monthGroup) use ($packetName) {

                    return [
                        'nama_pembayaran' => ($packetName) . " - " . ($monthGroup->first()->month_name),

                        'nominal' => $monthGroup->sum('total_bulan'),
                        'bulan_ids' => $monthGroup->pluck('bulan_id')->toArray(),
                        // 'period_period_id' => $monthGroup->first()->period_period_id,
                    ];
                });


                // return $detail->values();
                return $detail->values()->all();
            });

            $token = JWTAuth::parseToken();
            $claims = $token->getPayload();
            $kode_sekolah = $claims->get('kode_sekolah');

            // Eager load necessary relations and avoid extra queries
            $bulan = Bulan::with(['payment.pos', 'payment.period'])
                ->where('student_student_id', $user->student_id)
                ->where('bulan_status', 0)
                ->where('flip_status', 'READY')
                ->whereHas('payment', function ($query) {
                    $query->where('payment_is_batch', '0');
                })
                ->get()
                ->map(function ($item) use ($kode_sekolah) {
                    $payment = $item->payment;
                    $posName = $payment->pos->pos_name; // Eager loaded pos_name
                    $bulanName = $item->month->month_name;
                    $period = $payment->period;
                    $tahun = $period->period_start . '/' . $period->period_end;
                    return [
                        'bulan_id' => $item->bulan_id,
                        'nama_pembayaran' => $posName . ' ' . $bulanName . ' ' . $tahun,
                        'nominal' => (int) $item->bulan_bill
                    ];
                });

            //dd($bulan);


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


            $bebas = Bebas::where('student_student_id', $user->student_id)->get();

            // Eager load BebasPayMobile and associated data
            $bebas_pay_mobile = BebasPayMobile::with(['bebas', 'bebas.payment.pos'])
                ->whereIn('bebas_bebas_id', $bebas->pluck('bebas_id'))
                ->where('flip_status', 'Ready')
                ->get()
                ->map(function ($item) {
                    $bebasItem = $item->bebas;  // Eager loaded bebas data
                    $payment = $bebasItem->payment;  // Eager loaded payment
                    $period = $payment->period;
                    $posName = $payment->pos->pos_name;  // Eager loaded pos_name
                    return [
                        // 'bebas_id' => $item->bebas_pay_id,
                        'bebas_id' => $item->bebas_bebas_id,
                        'nama_pembayaran' => $posName . ' ' . $period->period_start . '/' . $period->period_end,
                        'nominal' => (int) $item->bebas_pay_bill
                    ];
                });
            $bebas_id = $bebas_pay_mobile;
            // dd($bebas_id);

            $noref = BebasPayMobile::with(['bebas', 'bebas.payment.pos'])
                ->whereIn('bebas_bebas_id', $bebas->pluck('bebas_id'))
                ->where('flip_status', 'Ready')
                ->first();
            // dd($noref);

            $totalNominal = $bulan->sum('nominal') + $bebas_pay_mobile->sum('nominal') + $groupedResult->sum('nominal');

            //jika $bulan atau $bebas_pay_mobile sudah ada isi atau salah satu ada isi  nya maka  update flip_no_trans dari flip_transaksi dari field id_transaksi
            if ($bulan || $bebas_pay_mobile) {
                $validator = Validator::make($request->all(), [
                    'kode_pondok' => 'nullable',
                    'noref' => 'nullable',
                    'tanggal' => 'nullable',
                    'student_id' => 'nullable',
                    'nominal' => 'nullable',
                    'status' => 'nullable',
                    'di_bayar' => 'nullable',
                    'va_no' => 'nullable',
                    'va_name' => 'nullable',
                    'va_channel' => 'nullable',
                    'va_bank' => 'nullable',
                    'transctionald' => 'nullable',
                    'Expired' => 'nullable',
                    'create_at' => 'nullable'
                ]);

                if ($validator->fails()) {
                    return response()->json([
                        'is_correct' => false,
                        'message' => $validator->errors()
                    ]);
                }
                //  dd($kode_sekolah);
                $fee = 4500;

                $checker = FlipTransaksi::where('student_id', $user->student_id)->where('status', 'RINGKASAN')->first();

                if ($checker) {
                    $checker->update([
                        'nominal' => $totalNominal
                    ]);
                    $data = $checker;
                    // $checker = FlipTransaksi::update([
                    //     'nominal'=> $checker
                    // ])
                } else {
                    //  dd($waktuAsli);
                    $data = FlipTransaksi::create([
                        'kode_pondok' => $kode_sekolah,
                        'noref' => 'belum',
                        'tanggal' => $waktuAsli,
                        'student_id' => $user->student_id,
                        'nominal' => $totalNominal,
                        'status' => 'RINGKASAN',
                        'di_bayar' => 0,
                        'va_no' => 'belum',
                        'va_nama' => null,
                        'va_channel' => 'belum',
                        'va_bank' => 'belum',
                        'transactionId' => 'belum',
                        'Expired' => 'belum',
                        'va_fee' => 'belum',
                        // 'create_at' => '00-00-0000'
                    ]);
                }


                $id_transaksi = $data->id_transaksi;
                // dd($id_transaksi);
                $update_bulan = Bulan::whereIn('bulan_id', $bulan->pluck('bulan_id'))->get();
                // dd($update_bulan);
                $update_paket_ids = $result->pluck('bulan_id');
                $update_paket = Bulan::whereIn('bulan_id', $update_paket_ids)->get();
                //dd($update_paket);
                $update_bebas = $bebas_pay_mobile->pluck('bebas_id');
                //  dd($update_bebas);
                $update_bebas_records = BebasPayMobile::whereIn('bebas_bebas_id', $update_bebas)->get();
                //dd($update_bebas_records);

                if ($update_bulan->isNotEmpty() || $update_bebas_records->isNotEmpty() || $update_paket->isNotEmpty()) {
                    foreach ($update_bulan as $item) {
                        // Perbarui field flip_no_trans dengan nilai id_transaksi
                        $item->flip_no_trans = $id_transaksi;

                        // Simpan perubahan ke database
                        $item->save();
                    }
                    foreach ($update_paket as $item) {
                        $item->flip_no_trans = $id_transaksi;
                        $item->save();
                    }

                    foreach ($update_bebas_records as $item) {
                        // Cek apakah flip_status tidak sama dengan 'LUNAS'
                        if ($item->flip_status !== 'LUNAS'  && $item->flip_status !== 'PENDING') {
                            $item->flip_no_trans = $id_transaksi; // Update flip_no_trans
                        }
                        $item->save(); // Simpan item, terlepas dari flip_status
                    }
                }
            }
            return response()->json([
                'is_correct' => 'success',
                //  'noref' => $noref->bebas_pay_noref,
                'total_nominal' => $totalNominal,
                'no_trans' => $id_transaksi = $data->id_transaksi,
                'pembayaran' => [...$bulan, ...$bebas_pay_mobile, ...$groupedResult],
            ]);
        } else {
            $user = auth()->user();
            $result = Bulan::join('batch_item as bi', 'bi.batch_item_payment_id', '=', 'bulan.payment_payment_id')
                ->join('batchpayment as bp', 'bp.id', '=', 'bi.batch_item_batchpayment_id')
                ->join('month as m', 'm.month_id', '=', 'bulan.month_month_id')
                ->join('payment as p', 'p.payment_id', '=', 'bulan.payment_payment_id') // Tambahkan join ke tabel payment
                ->select(
                    'bp.name as nama_paket',
                    'bp.id as id_paket',
                    'bulan.bulan_id',
                    'm.month_id',
                    'm.month_name',
                    'p.period_period_id',
                    DB::raw('SUM(bulan.bulan_bill) as total_bulan')
                )
                ->where('bulan.student_student_id', $user->student_id)
                ->where('bulan.bulan_status', 0)
                ->where('bulan.ipaymu_status', 'READY')
                ->where('p.payment_is_batch', '1') // Tambahkan kondisi payment_is_batch
                ->orderBy('m.month_id', 'ASC');


            $result = $result
                ->groupBy('bp.name', 'bp.id', 'bulan.bulan_id', 'm.month_name', 'm.month_id', 'bulan.ipaymu_status', 'p.period_period_id',)
                ->get();
            // dd($result);
            $groupedResult = $result->groupBy('id_paket')->flatMap(function ($paket) {
                $packetName = $paket->first()->nama_paket;


                $detail = $paket->groupBy('month_name')->map(function ($monthGroup) use ($packetName) {

                    return [
                        'nama_pembayaran' => ($packetName) . " - " . ($monthGroup->first()->month_name),

                        'nominal' => $monthGroup->sum('total_bulan'),
                        'bulan_ids' => $monthGroup->pluck('bulan_id')->toArray(),
                        // 'period_period_id' => $monthGroup->first()->period_period_id,
                    ];
                });


                // return $detail->values();
                return $detail->values()->all();
            });
            //  dd($result);


            $token = JWTAuth::parseToken();
            $claims = $token->getPayload();
            $kode_sekolah = $claims->get('kode_sekolah');
            $bulan = Bulan::with(['payment.pos', 'payment.period'])
                ->where('student_student_id', $user->student_id)
                ->where('bulan_status', 0)
                ->where('ipaymu_status', 'READY')
                ->whereHas('payment', function ($query) {
                    $query->where('payment_is_batch', '0');
                })
                ->get()
                ->map(function ($item) use ($kode_sekolah) {
                    $payment = $item->payment;
                    $posName = $payment->pos->pos_name; // Eager loaded pos_name
                    $bulanName = $item->month->month_name;
                    $period = $payment->period;
                    $tahun = $period->period_start . '/' . $period->period_end;
                    return [
                        'bulan_id' => $item->bulan_id,
                        'nama_pembayaran' => $posName . ' ' . $bulanName . ' ' . $tahun,
                        'nominal' => (int) $item->bulan_bill
                    ];
                });

            $waktu = $user->waktu_indonesia;
            $waktuAsli = Carbon::now('Asia/jakarta')->format('Y-m-d H:i:s');
            // $waktu = 'WIB';
            //dd($waktu);

            if ($waktu == 'WIT') {
                $waktuAsli = Carbon::now('Asia/Makassar')->format('Y-m-d H:i:s');
            }
            if ($waktu == 'WITA') {
                $waktuAsli = Carbon::now('Asia/Jayapura')->format('Y-m-d H:i:s');
            }


            $bebas = Bebas::where('student_student_id', $user->student_id)->get();

            // Eager load BebasPayMobile and associated data
            $bebas_pay_mobile = BebasPayMobile::with(['bebas', 'bebas.payment.pos'])
                ->whereIn('bebas_bebas_id', $bebas->pluck('bebas_id'))
                ->where('ipaymu_status', 'READY')
                ->get()
                ->map(function ($item) {
                    $bebasItem = $item->bebas;  // Eager loaded bebas data
                    $payment = $bebasItem->payment;  // Eager loaded payment
                    $period = $payment->period;
                    $posName = $payment->pos->pos_name;  // Eager loaded pos_name
                    return [
                        // 'bebas_id' => $item->bebas_pay_id,
                        'bebas_id' => $item->bebas_bebas_id,
                        'nama_pembayaran' => $posName . ' ' . $period->period_start . '/' . $period->period_end,
                        'nominal' => (int) $item->bebas_pay_bill
                    ];
                });
            $bebas_id = $bebas_pay_mobile;
            //  dd($bebas_pay_mobile);

            $noref = BebasPayMobile::with(['bebas', 'bebas.payment.pos'])
                ->whereIn('bebas_bebas_id', $bebas->pluck('bebas_id'))
                ->where('ipaymu_status', 'READY')
                ->first();
            // dd($noref);

            $totalNominal = $bulan->sum('nominal') + $bebas_pay_mobile->sum('nominal') + $groupedResult->sum('nominal');
            // dd($totalNominal);


            if ($bulan || $bebas_pay_mobile) {
                $validator = Validator::make($request->all(), [
                    'kode_pondok' => 'nullable',
                    'noref' => 'nullable',
                    'tanggal' => 'nullable',
                    'student_id' => 'nullable',
                    'nominal' => 'nullable',
                    'status' => 'nullable',
                    'di_bayar' => 'nullable',
                    'va_no' => 'nullable',
                    'va_name' => 'nullable',
                    'va_channel' => 'nullable',
                    'va_bank' => 'nullable',
                    'transctionald' => 'nullable',
                    'Expired' => 'nullable',
                    'create_at' => 'nullable'
                ]);

                if ($validator->fails()) {
                    return response()->json([
                        'is_correct' => false,
                        'message' => $validator->errors()
                    ]);
                }
                //  dd($kode_sekolah);
                $fee = 4500;

                $checker = IpaymuTransaksi::where('student_id', $user->student_id)->where('status', 'RINGKASAN')->first();

                if ($checker) {
                    $checker->update([
                        'nominal' => $totalNominal
                    ]);
                    $data = $checker;
                    // $checker = FlipTransaksi::update([
                    //     'nominal'=> $checker
                    // ])
                } else {

                    $data = IpaymuTransaksi::create([
                        'kode_pondok' => $kode_sekolah,
                        'noref' => 'belum',
                        'tanggal' => $waktuAsli,
                        'student_id' => $user->student_id,
                        'nominal' => $totalNominal,
                        'status' => 'RINGKASAN',
                        'di_bayar' => 0,
                        'va_no' => 'belum',
                        'va_nama' => null,
                        'va_channel' => 'belum',
                        'va_bank' => 'belum',
                        'transactionId' => 'belum',
                        'Expired' => 'belum',
                        'va_fee' => 'belum',
                        // 'create_at' => '00-00-0000'
                    ]);
                }


                $id_transaksi = $data->id_transaksi;
                // dd($id_transaksi);
                $update_bulan = Bulan::whereIn('bulan_id', $bulan->pluck('bulan_id'))->get();
                // dd($update_bulan);
                $update_paket_ids = $result->pluck('bulan_id');
                $update_paket = Bulan::whereIn('bulan_id', $update_paket_ids)->get();
                //dd($update_paket);
                $update_bebas = $bebas_pay_mobile->pluck('bebas_id');
                //  dd($update_bebas);
                $update_bebas_records = BebasPayMobile::whereIn('bebas_bebas_id', $update_bebas)->get();
                //dd($update_bebas_records);

                if ($update_bulan->isNotEmpty() || $update_bebas_records->isNotEmpty() || $update_paket->isNotEmpty()) {
                    foreach ($update_bulan as $item) {
                        // Perbarui field flip_no_trans dengan nilai id_transaksi
                        $item->ipaymu_no_trans = $id_transaksi;

                        // Simpan perubahan ke database
                        $item->save();
                    }
                    foreach ($update_paket as $item) {
                        $item->ipaymu_no_trans = $id_transaksi;
                        $item->save();
                    }

                    foreach ($update_bebas_records as $item) {
                        // Cek apakah flip_status tidak sama dengan 'LUNAS'
                        if ($item->ipaymu_status !== 'LUNAS'  && $item->ipaymu_status !== 'PENDING') {
                            $item->ipaymu_no_trans = $id_transaksi; // Update flip_no_trans
                        }
                        $item->save(); // Simpan item, terlepas dari flip_status
                    }
                }
            }
            return response()->json([
                'is_correct' => 'success',
                //  'noref' => $noref->bebas_pay_noref,
                'total_nominal' => $totalNominal,
                'no_trans' => $id_transaksi = $data->id_transaksi,
                'pembayaran' => [...$bulan, ...$bebas_pay_mobile, ...$groupedResult],
            ]);
        }
    }


    public function index1()
    {
        $user = auth()->user();
        //claim payload
        $token = JWTAuth::parseToken();

        // Get the token payload
        $claims = $token->getPayload();

        $kode_sekolah = $claims->get('kode_sekolah');
        $bulan = Bulan::with(['payment', 'payment.pos', 'payment.period'])->where('student_student_id', $user->student_id)->where('flip_status', 'Ready')->get()->map(function ($item) use ($kode_sekolah) {
            $payment = $item->payment;
            $posName = $payment->pos;  // Akses pos yang sudah di-eager load
            $period = $payment->period;  // Akses period yang sudah di-eager load

            $tahun = $period->period_start . '/' . $period->period_end;
            return [
                'bulan_id' => $item->bulan_id,
                'kode_pondok' => $kode_sekolah,
                'nama_pembayaran' => $posName->pos_name . ' ' . $tahun,
                'noref' => $item->bulan_noref,
                'student_id' => $item->student_student_id,
                'nominal' => (int) $item->bulan_bill
            ];
        });
        $bebas = Bebas::where('student_student_id', $user->student_id)->get()->map(function ($item) {
            return [
                'bebas_id' => $item->bebas_id,
                //'student_id' => $item->student_student_id
            ];
        });
        // dd($bebas);
        //cek apakah

        //cek apakah bebas_id juga berada di bebas_pay_mobile
        $bebas_pay_mobile = BebasPayMobile::whereIn('bebas_bebas_id', $bebas)->get()->map(function ($item) {
            $bebas_id = $item->bebas_bebas_id;

            // Ambil koleksi bebas yang sesuai dengan bebas_id
            $bebas = Bebas::whereIn('bebas_id', [$bebas_id])->get();  // Mengambil semua entri bebas yang sesuai

            if ($bebas->isNotEmpty()) {  // Pastikan koleksi tidak kosong
                // Iterasi koleksi bebas
                foreach ($bebas as $bebasItem) {
                    $payment_id = $bebasItem->payment_payment_id;  // Akses payment_payment_id
                    dump($payment_id);  // Menampilkan payment_id

                    // Ambil koleksi payment yang sesuai dengan payment_id
                    $payments = Payment::where('payment_id', $payment_id)->get();  // Mengambil koleksi payment

                    if ($payments->isNotEmpty()) {  // Pastikan koleksi payments tidak kosong
                        // Iterasi koleksi payment
                        foreach ($payments as $payment) {
                            $pos_id = $payment->pos_pos_id;  // Akses pos_pos_id
                            dump($pos_id);  // Menampilkan pos_pos_id

                            // Ambil koleksi Pos berdasarkan pos_id
                            $pos = Pos::where('pos_id', $pos_id)->get();  // Ambil koleksi Pos

                            if ($pos->isNotEmpty()) {
                                // Ambil nama dari Pos (misalnya, pos_name)
                                $posName = $pos->first()->pos_name;  // Mengambil pos_name dari elemen pertama
                                dump($posName);  // Menampilkan pos_name
                            } else {
                                dump('No Pos found for pos_id: ' . $pos_id);  // Jika Pos tidak ditemukan
                            }
                        }
                    } else {
                        dump('No payments found for payment_id: ' . $payment_id);  // Jika tidak ditemukan pembayaran untuk payment_id
                    }
                }
            } else {
                dump('No data found for bebas_id: ' . $bebas_id);  // Jika tidak ada data ditemukan untuk bebas_id
            }


            return [
                'bebas_pay_id' => $item->bebas_pay_id,
                'student_id' => $item->user_user_id,
                'kode_pondok' => $item->sekolah_id,
                'bebas_pay_noref' => $item->bebas_pay_noref,
                'nama_pembayaran' => $posName,
                'nominal' => $item->bebas_pay_bill
            ];
        });
        if ($bulan) {
            return response()->json([
                'is_correct' => 'success',
                'message' => 'Data berhasil diambil',
                'Bulan' => $bulan,
                'Bebas' => $bebas_pay_mobile
            ]);
        }
    }
}
