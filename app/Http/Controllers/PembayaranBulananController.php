<?php

namespace App\Http\Controllers;

use App\Models\Pos;
use App\Models\Bulan;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;


class PembayaranBulananController extends Controller
{

    public function indexV2(Request $request)
    {
        $setting = Setting::where('setting_name', 'api_secret_key')->first();
        //$data = Bulan::where('bulan_status', 0)->get();
        // dd($setting);
        if ($setting) {
            //  dd('coba');
            $token = JWTAuth::parseToken();

            // Get the token payload
            $claims = $token->getPayload();
            $payment = $claims->get('payment');
            //dd($claims);
            $user = auth()->user();
            // $data = Bulan::where('student_student_id', $user->student_id)->where('bulan_status', 0)->get();
            // dd($data);
            // Ambil periodId dari query string, jika ada
            $periodId = $request->query('period_id');  // Gantilah dengan nama parameter yang sesuai, misalnya 'period_id'

            $bulanDataQuery = Bulan::with([
                'month',
                'payment',
                'payment.pos',
                'payment.period',
                'student',
                'account',
                'pos',
            ])
                ->where('bulan.student_student_id', $user->student_id)
                ->where('bulan.bulan_status', 0)
                ->where(function ($query) {
                    $query->where('flip_status', '!=', 'PENDING')
                        ->orWhereNull('flip_status');
                })
                // ->where(function ($query) {
                //     $query->where('flip_status', '!=', 'LUNAS')
                //         ->orWhereNull('flip_status');  // Memastikan flip_status valid
                // })
                ->whereHas('payment', function ($query) {
                    $query->where('payment_is_batch', '0');  // Batch tidak terproses
                })
                ->orderBy('bulan.month_month_id', 'asc') // Mengurutkan berdasarkan status
                ->orderBy('bulan.payment_payment_id', 'asc');

            // Jika periodId ada, filter berdasarkan period_period_id
            if ($periodId) {
                $bulanDataQuery->whereHas('payment', function ($query) use ($periodId) {
                    $query->where('period_period_id', $periodId);
                });
            }

            $bulanData = $bulanDataQuery->get();


            $formattedData = $bulanData->map(function ($bulan) {
                $payment = $bulan->payment;
                $posName = $payment->pos ? $payment->pos->pos_name : 'Pos not available';
                $period = $payment->period;
                $tahun = $period ? $period->period_start . '/' . $period->period_end : 'Unknown period';

                return [
                    'month_name' => $bulan->month->month_name . ' (' . $tahun . ')',
                    'detail_bulan' => [
                        'row' => $posName . ' ' . $bulan->month->month_name . ' (' . $tahun . ')',
                        'bulan_bill' => $bulan->bulan_bill,
                        'bulan_id' => $bulan->bulan_id,
                        'period' => $period ? $period->period_id : 'Unknown period',
                        'status' => (bool) $bulan->bulan_status,
                        'is_in_cart' => $bulan->flip_status === 'READY' ? true : false
                    ]
                ];
            })->groupBy('month_name')->map(function ($items, $monthName) {
                return [
                    'nama_bulan' => $monthName,
                    'daftar_pembayaran' => $items->pluck('detail_bulan')
                ];
            });
            $response = [
                'detail' => $formattedData->values() // Ambil bulan pertama (contoh: Februari)

            ];

            // dd($formattedData);

            return response()->json([
                'is_correct' => true,
                'payment' => $payment,
                'pembayaran' => 'bulanan',
                'detail' => $response['detail'],

            ], 200);
        } else {
            $token = JWTAuth::parseToken();

            // Get the token payload
            $claims = $token->getPayload();
            $payment = $claims->get('payment');
            //dd($claims);
            $user = auth()->user();

            // Ambil periodId dari query string, jika ada
            $periodId = $request->query('period_id');  // Gantilah dengan nama parameter yang sesuai, misalnya 'period_id'

            $bulanDataQuery = Bulan::with([
                'month',
                'payment',
                'payment.pos',
                'payment.period',
                'student',
                'account',
            ])
                ->where('bulan.student_student_id', $user->student_id)
                ->where('bulan.bulan_status', 0)
                // ->where(function ($query) {
                //     $query->where('flip_status', '!=', 'LUNAS')
                //         ->orWhereNull('flip_status');  // Memastikan flip_status valid
                // })
                ->whereHas('payment', function ($query) {
                    $query->where('payment_is_batch', '0');  // Batch tidak terproses
                })
                ->orderBy('bulan.bulan_status', 'asc');  // Mengurutkan berdasarkan status

            // Jika periodId ada, filter berdasarkan period_period_id
            if ($periodId) {
                $bulanDataQuery->whereHas('payment', function ($query) use ($periodId) {
                    $query->where('period_period_id', $periodId);
                });
            }

            $bulanData = $bulanDataQuery->get();


            $formattedData = $bulanData->map(function ($bulan) {
                $payment = $bulan->payment;
                $posName = $payment->pos ? $payment->pos->pos_name : 'Pos not available';
                $period = $payment->period;
                $tahun = $period ? $period->period_start . '/' . $period->period_end : 'Unknown period';

                return [
                    'month_name' => $bulan->month->month_name . ' (' . $tahun . ')',
                    'detail_bulan' => [
                        'row' => $posName . ' ' . $bulan->month->month_name . ' (' . $tahun . ')',
                        'bulan_bill' => $bulan->bulan_bill,
                        'bulan_id' => $bulan->bulan_id,
                        'period' => $period ? $period->period_id : 'Unknown period',
                        'status' => (bool) $bulan->bulan_status,
                        'is_in_cart' => $bulan->flip_status === 'READY' ? true : false
                    ]
                ];
            })->groupBy('month_name')->map(function ($items, $monthName) {
                return [
                    'nama_bulan' => $monthName,
                    'daftar_pembayaran' => $items->pluck('detail_bulan')
                ];
            });
            $response = [
                'detail' => $formattedData->values() // Ambil bulan pertama (contoh: Februari)

            ];

            return response()->json([
                'is_correct' => true,
                'payment' => $payment,
                'pembayaran' => 'bulanan',
                'detail' => $response['detail'],

            ], 200);
        }
    }

    public function index(Request $request)
    {
        $setting = Setting::where('setting_name', 'api_secret_key')->first();
        //$data = Bulan::where('bulan_status', 0)->get();
        // dd($setting);
        if ($setting) {
            //  dd('coba');
            $token = JWTAuth::parseToken();

            // Get the token payload
            $claims = $token->getPayload();
            $payment = $claims->get('payment');
            //dd($claims);
            $user = auth()->user();

            // Ambil periodId dari query string, jika ada
            $periodId = $request->query('period_id');  // Gantilah dengan nama parameter yang sesuai, misalnya 'period_id'

            $bulanDataQuery = Bulan::with([
                'month',
                'payment',
                'payment.pos',
                'payment.period',
                'student',
                'account',
            ])
                ->where('bulan.student_student_id', $user->student_id)
                ->where('bulan.bulan_status', 0)
                // ->where(function ($query) {
                //     $query->where('flip_status', '!=', 'LUNAS')
                //         ->orWhereNull('flip_status');  // Memastikan flip_status valid
                // })
                ->whereHas('payment', function ($query) {
                    $query->where('payment_is_batch', '0');  // Batch tidak terproses
                })
                ->orderBy('bulan.bulan_status', 'asc');  // Mengurutkan berdasarkan status

            // Jika periodId ada, filter berdasarkan period_period_id
            if ($periodId) {
                $bulanDataQuery->whereHas('payment', function ($query) use ($periodId) {
                    $query->where('period_period_id', $periodId);
                });
            }

            $bulanData = $bulanDataQuery->get();
            //  dd($bulanData);

            $formattedData = $bulanData->map(function ($bulan) {
                $payment = $bulan->payment;
                $posName = $payment->pos ? $payment->pos->pos_name : 'Pos not available';
                $period = $payment->period;


                // Format periode (tahun)
                $tahun = $period ? $period->period_start . '/' . $period->period_end : 'Unknown period';

                return [
                    'detail_bulan' => [
                        'row' => $posName . ' ' . $bulan->month->month_name . ' (' . $tahun . ')',
                        'bulan_bill' => $bulan->bulan_bill,
                        'bulan_id' => $bulan->bulan_id,
                        'period' => $period ? $period->period_id : 'Unknown period',
                        'status' => (bool) $bulan->bulan_status,
                        'is_in_cart' => $bulan->flip_status === 'READY' ? true : false
                    ]
                ];
            });
            // dd($formattedData);

            return response()->json([
                'is_correct' => true,
                'payment' => $payment,
                'pembayaran' => 'bulanan',
                'detail' => $formattedData,

            ], 200);
        } else {
            //  dd('coba');
            $token = JWTAuth::parseToken();

            // Get the token payload
            $claims = $token->getPayload();
            $payment = $claims->get('payment');
            //dd($claims);
            $user = auth()->user();

            // Ambil periodId dari query string, jika ada
            $periodId = $request->query('period_id');  // Gantilah dengan nama parameter yang sesuai, misalnya 'period_id'

            $bulanDataQuery = Bulan::with([
                'month',
                'payment',
                'payment.pos',
                'payment.period',
                'student',
                'account',
            ])
                ->where('bulan.student_student_id', $user->student_id)
                ->where('bulan.bulan_status', 0)
                // ->where(function ($query) {
                //     $query->where('flip_status', '!=', 'LUNAS')
                //         ->orWhereNull('flip_status');  // Memastikan flip_status valid
                // })
                ->whereHas('payment', function ($query) {
                    $query->where('payment_is_batch', '0');  // Batch tidak terproses
                })
                ->orderBy('bulan.bulan_status', 'asc');  // Mengurutkan berdasarkan status

            // Jika periodId ada, filter berdasarkan period_period_id
            if ($periodId) {
                $bulanDataQuery->whereHas('payment', function ($query) use ($periodId) {
                    $query->where('period_period_id', $periodId);
                });
            }

            $bulanData = $bulanDataQuery->get();

            $formattedData = $bulanData->map(function ($bulan) {
                $payment = $bulan->payment;
                $posName = $payment->pos ? $payment->pos->pos_name : 'Pos not available';
                $period = $payment->period;


                // Format periode (tahun)
                $tahun = $period ? $period->period_start . '/' . $period->period_end : 'Unknown period';

                return [
                    'detail_bulan' => [
                        'row' => $posName . ' ' . $bulan->month->month_name . ' (' . $tahun . ')',
                        'bulan_bill' => $bulan->bulan_bill,
                        'bulan_id' => $bulan->bulan_id,
                        'period' => $period ? $period->period_id : 'Unknown period',
                        'status' => (bool) $bulan->bulan_status,
                        'is_in_cart' => $bulan->ipaymu_status === 'READY' ? true : false
                    ]
                ];
            });
            // dd($formattedData);

            return response()->json([
                'is_correct' => true,
                'payment' => $payment,
                'pembayaran' => 'bulanan',
                'detail' => $formattedData,

            ], 200);
        }

        //  }
    }

    public function lunas1(Request $request)
    {
        $user = auth()->user();

        // Ambil periodId dari query string, jika ada
        $periodId = $request->query('period_id');  // Gantilah dengan nama parameter yang sesuai, misalnya 'period_id'

        // Query dasar untuk Bulan
        $bulanDataQuery = Bulan::with([
            'month',
            'payment', // Memastikan relasi payment dimuat
            'payment.pos',
            'payment.period',
            'student',
            'account',
        ])
            ->where('bulan.student_student_id', $user->student_id)
            ->where('flip_status', 'LUNAS')
            ->whereHas('payment', function ($query) {
                $query->where('payment_is_batch', '0');  // Memastikan hanya batch yang tidak terproses
            })
            ->orderBy('bulan.bulan_status', 'asc');  // Mengurutkan berdasarkan status

        // Jika periodId ada, filter berdasarkan period_period_id
        if ($periodId) {
            $bulanDataQuery->whereHas('payment', function ($query) use ($periodId) {
                $query->where('period_period_id', $periodId);  // Filter berdasarkan period_period_id
            });
        }

        // Ambil data Bulan berdasarkan query yang sudah difilter
        $bulanData = $bulanDataQuery->get();

        // Cek jika data tidak kosong
        if ($bulanData->isNotEmpty()) {
            // Format data yang akan dikirimkan
            $formattedData = $bulanData->map(function ($bulan) {
                $payment = $bulan->payment;
                $posName = $payment->pos ? $payment->pos->pos_name : 'Pos not available';
                $period = $payment->period;

                //format bulan
                $monthName = $bulan->month ? $bulan->month->month_name : 'Unknown month';

                // Format periode (tahun)
                $tahun = $period ? $period->period_start . '/' . $period->period_end : 'Unknown period';

                return [
                    'detail_bulan' => [
                        'row' =>  $posName . ' ' . $monthName . ' - ' . $tahun,
                        'bulan_bill' => $bulan->bulan_bill,
                        'bulan_id' => $bulan->bulan_id,
                        'period' => $period ? $period->period_id : 'Unknown period',
                        'status' => (bool) $bulan->bulan_status,
                        'is_in_cart' => $bulan->flip_status
                    ]
                ];
            });

            return response()->json([
                'is_correct' => true,
                'pembayaran' => 'bulanan',
                'detail' => $formattedData
            ], 200);
        } else {
            // Jika data kosong, return pesan error
            return response()->json([
                'is_correct' => false,
                'message' => 'Data not found',
                'data' => []
            ]);
        }
    }

    public function lunas(Request $request)
    {
        $user = auth()->user();

        // Ambil periodId dari query string, jika ada
        $periodId = $request->query('period_id');

        // Query dasar untuk Bulan
        $bulanDataQuery = Bulan::with([
            'month',   // Relasi bulan
            'payment',
            'payment.pos',
            'payment.period',
            'student',
            'account',
        ])
            ->where('bulan.student_student_id', $user->student_id)
            ->where('bulan_status', 1)
            // ->where('flip_status', 'LUNAS')
            ->whereHas('payment', function ($query) {
                $query->where('payment_is_batch', '0');
            })
            ->orderBy('bulan.bulan_status', 'asc');

        // Jika periodId ada, filter berdasarkan period_period_id
        if ($periodId) {
            $bulanDataQuery->whereHas('payment', function ($query) use ($periodId) {
                $query->where('period_period_id', $periodId);
            });
        }

        $bulanData = $bulanDataQuery->get();

        //if ($bulanData->isNotEmpty()) {
        // Grupkan data berdasarkan pos_name
        $groupedByPosName = $bulanData->groupBy(function ($item) {
            return $item->payment->pos->pos_name ?? 'Unknown pos';
        });

        // Format data sesuai kebutuhan
        $formattedData = $groupedByPosName->map(function ($items, $posName) {
            // Format daftar riwayat pembayaran
            $riwayatPembayaran = $items->map(function ($item) {
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
            });

            return [
                'pos_name' => $posName,
                'daftar_riwayat' => $riwayatPembayaran->toArray()
            ];
        })->values(); // Ubah menjadi array tanpa kunci

        return response()->json([
            'is_correct' => true,
            'message' => 'Data anda valid',
            'bulan_history' => $formattedData,
        ], 200);
    }



    public function update(Request $request, $id)
    {
        // $token = JWTAuth::parseToken();
        $setting = Setting::where('setting_name', 'api_secret_key')->first();
        if ($setting) {
            $bulan = Bulan::where('bulan_id', $id)->first();
            //dd($bulan->month->month_id);
            //cek apakah month_month_id yang lebih kecil ada\
            // $bulanCheck = Bulan::where('month_month_id', '<', $bulan->month->month_id)
            //     ->where('payment_payment_id', $bulan->payment_payment_id)
            //     ->where('student_student_id', $bulan->student_student_id)
            //     ->where('bulan_status', 0)
            //     ->first();

            if ($bulan) {
                // cek pembayaran sudah harus lunas pembayaran bulan sebelum nya
                // if ($bulanCheck) {
                //     // Jika ada bulan yang belum lunas
                //     // $namnaBulan = $bulanCheck->month->month_name;
                //     return response()->json(['message' => 'Pembayaran bulan ' . $bulanCheck->month->month_name . ' belum lunas.'], 400);
                // }
                $newStatus = $bulan->flip_status === 'READY' ? null : 'READY';
                $flip_no_trans = $bulan->flip_no_trans !== null ? null : null;
                //dd($flip_no_trans);
                // Update status flip_status dengan nilai baru
                $bulan->update(['flip_status' => $newStatus, 'flip_no_trans' => $flip_no_trans]);

                // Mengembalikan respons sukses
                return response()->json(['message' => 'Flip status updated successfully!'], 200);
            } else {
                // Jika data tidak ditemukan
                return response()->json(['message' => 'Data not found.'], 404);
            }
        } else {
            $bulan = Bulan::where('bulan_id', $id)->first();
            //  dd($bulan);
            if ($bulan) {

                $newStatus = $bulan->ipaymu_status === 'READY' ? null : 'READY';

                //cek apakah no transaksi sudah ada jika ada update jadi null
                $newStatusno = $bulan->ipaymu_no_trans !== null ? null : null;
                // Update status flip_status dengan nilai baru
                $bulan->update(['ipaymu_status' => $newStatus]);
                $bulan->update(['ipaymu_no_trans' => $newStatusno]);

                // Mengembalikan respons sukses
                return response()->json(['message' => 'ipaymu status updated successfully!'], 200);
            } else {
                // Jika data tidak ditemukan
                return response()->json(['message' => 'Data not found.'], 404);
            }
        }
    }
}
