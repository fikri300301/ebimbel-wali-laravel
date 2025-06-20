<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Bulan;
use App\Models\Setting;
use App\Models\BebasPay;
use App\Models\BebasPayMobile;
use App\Models\FlipChannel;
use Illuminate\Http\Request;
use App\Models\FlipTransaksi;
use App\Models\IpaymuTransaksi;
use App\Models\data_ipaymu_channel;

class RiwayatTransaksiController extends Controller
{
    // public function index(Request $request)
    // {
    //     $user = auth()->user();

    //     // Ambil input start_date dan end_date dari request
    //     $startDate = $request->input('start_date');
    //     $endDate = $request->input('end_date');

    //     // Pastikan end_date mencakup waktu hingga akhir hari
    //     if ($endDate) {
    //         $endDate = Carbon::parse($endDate)->endOfDay(); // Tambahkan waktu 23:59:59
    //     }

    //     $data = FlipTransaksi::where('student_id', $user->student_id)
    //         ->where('status', '!=', 'RINGKASAN')
    //         ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
    //             $query->whereBetween('tanggal', [$startDate, $endDate]); // Filter dengan rentang tanggal
    //         })
    //         ->get()
    //         ->map(function ($item) {
    //             return [
    //                 'id' => $item->id_transaksi,
    //                 'status' => $item->status,
    //                 'nominal' => $item->nominal,
    //                 'tanggal' => $item->tanggal,
    //             ];
    //         });

    //     return response()->json([
    //         'is_correct' => true,
    //         'message' => 'success',
    //         'riwayat' => $data
    //     ]);
    // }
    public function index(Request $request, $start_date = null, $end_date = null)
    {
        // dd('cobacoba');
        $setting = Setting::where('setting_name', 'api_secret_key')->first();
        if ($setting) {
            $user = auth()->user();

            // Jika parameter tanggal tidak diberikan, ambil dari request
            if (!$start_date) {
                $start_date = $request->input('start_date');
            }
            if (!$end_date) {
                $end_date = $request->input('end_date');
            }

            // Inisialisasi query dasar
            $query = FlipTransaksi::where('student_id', $user->student_id)
                ->whereIn('status', ['SUCCESSFUL', 'FAILED', 'PENDING']);
            // ->whereNotIn('status', ['RINGKASAN', 'READY']);

            // Tambahkan filter berdasarkan parameter yang diisi
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
            $user = auth()->user();

            // Jika parameter tanggal tidak diberikan, ambil dari request
            if (!$start_date) {
                $start_date = $request->input('start_date');
            }
            if (!$end_date) {
                $end_date = $request->input('end_date');
            }

            // Inisialisasi query dasar
            $query = IpaymuTransaksi::where('student_id', $user->student_id)
                ->whereIn('status', ['berhasil', 'FAILED', 'PENDING']); //masih ada tambahan
            // ->whereNotIn('status', ['RINGKASAN', 'READY']);

            // Tambahkan filter berdasarkan parameter yang diisi
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

    public function Pembatalan($id)
    {
        $setting = Setting::where('setting_name', 'api_secret_key')->first();
        if ($setting) {
            $user = auth()->user();
            $data = FlipTransaksi::where('student_id', $user->student_id)->where('id_transaksi', $id)->first();

            //update status flip_transksi menjadi gagal dan bulan atau bebas flip_no_trans dan flip_status menjadi kosong
            if ($data) {
                $data->update([
                    'status' => 'FAILED',
                ]);
                $ids = is_array($id) ? $id : [$id];

                $bulans = Bulan::whereIn('flip_no_trans', $ids)->get();
                // dd(Bulan::where('student_student_id', 1)->where('bulan_id', 99)->get());
                $bulanId = $bulans->pluck('bulan_id')->toArray();
                if (!empty($bulanId)) {
                    Bulan::whereIn('bulan_id', $bulanId)
                        ->update([
                            'flip_no_trans' => null,
                            'flip_status' => null
                        ]);
                }

                $bebasPays = BebasPayMobile::whereIn('flip_no_trans', $ids)->get();
                $bebasId = $bebasPays->pluck('bebas_pay_id')->toArray();
                if (!empty($bebasId)) {
                    BebasPayMobile::whereIn('bebas_pay_id', $bebasId)
                        ->update([
                            'flip_no_trans' => null,
                            'flip_status' => null
                        ]);
                }
            };
            return response()->json([
                'is_correct' => true,
                'message' => 'success',
            ]);
        } else {
            $user = auth()->user();
            $data = IpaymuTransaksi::where('student_id', $user->student_id)->where('id_transaksi', $id)->first();

            //update status flip_transksi menjadi gagal dan bulan atau bebas flip_no_trans dan flip_status menjadi kosong
            if ($data) {
                $data->update([
                    'status' => 'FAILED',
                ]);
                $ids = is_array($id) ? $id : [$id];
                $bulans = Bulan::whereIn('ipaymu_no_trans', $ids)->get();
                $bulanId = $bulans->pluck('bulan_id')->toArray();
                if (!empty($bulanId)) {
                    Bulan::whereIn('bulan_id', $bulanId)
                        ->update([
                            'ipaymu_no_trans' => null,
                            'ipaymu_status' => null
                        ]);
                }

                $bebasPays = BebasPay::whereIn('ipaymu_no_trans', $ids)->get();
                $bebasId = $bebasPays->pluck('bebas_pay_id')->toArray();
                if (!empty($bebasId)) {
                    BebasPay::whereIn('bebas_pay_id', $bebasId)
                        ->update([
                            'ipaymu_no_trans' => null,
                            'ipaymu_status' => null
                        ]);
                }
            };
            return response()->json([
                'is_correct' => true,
                'message' => 'success',
            ]);
        }
    }

    public function detailV2($id)
    {
        $setting = Setting::where('setting_name', 'api_secret_key')->first();
        if ($setting) {
            $user = auth()->user();
            $data = FlipTransaksi::where('student_id', $user->student_id)->where('id_transaksi', $id)->first();
            $fee = $data->va_fee;
            $bank = strtoupper($data->va_bank);
            // dd($bank);
            $fotoBank = FlipChannel::where('kode', $bank)->first();
            $bulanDataQuery = Bulan::with([
                'month',   // Relasi bulan
                'payment',
                'payment.pos',
                'payment.period',
                'student',
                'account',
            ])
                ->where('bulan.flip_no_trans', $id)
                ->whereHas('payment', function ($query) {
                    $query->where('payment_is_batch', '0');
                })
                ->orderBy('bulan.bulan_status', 'asc');

            $bulanData = $bulanDataQuery->get();

            // Format all payment items into a single array
            $daftarRiwayat = $bulanData->map(function ($item) {
                $payment = $item->payment;
                $posName = $payment->pos->pos_name ?? 'Unknown pos';
                $period = $payment->period;
                $tahun = $period ? $period->period_start . '/' . $period->period_end : 'Unknown period';
                $monthName = $item->month ? $item->month->month_name : 'Unknown month';

                // Format nama pembayaran
                $namaPembayaran = "{$posName} {$monthName} - {$tahun}";

                return [
                    'nama' => $namaPembayaran,
                    'nominal' => (int)$item->bulan_bill,
                ];
            })->toArray();
            // dd($fotoBank->logo);
            if ($data) {
                return response()->json([
                    'is_correct' => true,
                    'message' => 'success',
                    'status' => $data->status,
                    'total_pembayaran' => $data->nominal,
                    'daftar_riwayat' => $daftarRiwayat,
                    'mitra' => $data->va_bank,
                    'noVa' => $data->va_no,
                    'tenggat' => $data->Expired,
                    'biaya' => $data->nominal - $fee,
                    'fee' => (int)$data->va_fee,
                    'foto' => 'https://mobile.epesantren.co.id/walsan/assets/logo_bank/' . $fotoBank->logo
                ]);
            }
        } else {
            $user = auth()->user();
            $data = IpaymuTransaksi::where('student_id', $user->student_id)->where('id_transaksi', $id)->first();
            $fee = $data->va_fee;
            $bank = strtoupper($data->va_bank);
            // dd($bank);
            $fotoBank = data_ipaymu_channel::where('kode', $bank)->first();
            // dd($fotoBank->logo);
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
                    'foto' => 'https://mobile.epesantren.co.id/walsan/assets/logo_bank/' . $fotoBank->logo
                ]);
            }
        }
    }

    public function detail($id)
    {
        $setting = Setting::where('setting_name', 'api_secret_key')->first();
        if ($setting) {
            $user = auth()->user();
            $data = FlipTransaksi::where('student_id', $user->student_id)->where('id_transaksi', $id)->first();
            $fee = $data->va_fee;
            $bank = strtoupper($data->va_bank);
            // dd($bank);
            $fotoBank = FlipChannel::where('kode', $bank)->first();
            // dd($fotoBank->logo);
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
                    'foto' => 'https://mobile.epesantren.co.id/walsan/assets/logo_bank/' . $fotoBank->logo
                ]);
            }
        } else {
            $user = auth()->user();
            $data = IpaymuTransaksi::where('student_id', $user->student_id)->where('id_transaksi', $id)->first();
            $fee = $data->va_fee;
            $bank = strtoupper($data->va_bank);
            // dd($bank);
            $fotoBank = data_ipaymu_channel::where('kode', $bank)->first();
            // dd($fotoBank->logo);
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
                    'foto' => 'https://mobile.epesantren.co.id/walsan/assets/logo_bank/' . $fotoBank->logo
                ]);
            }
        }
    }
}
