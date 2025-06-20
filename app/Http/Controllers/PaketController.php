<?php

namespace App\Http\Controllers;

use App\Models\Bulan;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;

class PaketController extends Controller
{
    public function index1()
    {
        $user = auth()->user();

        $bulanData = Bulan::with([
            'month',
            'payment', // Memastikan relasi payment dimuat
            'payment.pos',
            'payment.period',
            'student',
            'account',
        ])
            ->where('bulan.student_student_id', $user->student_id)
            ->whereHas('payment', function ($query) {
                $query->where('payment_is_batch', '1');
            })
            ->orderBy('bulan.bulan_status', 'asc')
            ->get();
        // dd($bulanData);
        $bulanData;

        // Cek jika data tidak kosong
        if ($bulanData->isNotEmpty()) {
            $formattedData = $bulanData->map(function ($bulan) {
                $payment = $bulan->payment;
                $posName = $payment->pos ? $payment->pos->pos_name : 'Pos not available';
                $period = $payment->period;

                $tahun = $period ? $period->period_start . '/' . $period->period_end : 'Unknown period';

                return [
                    'detail_paket' => [
                        'row' => $posName . ' ' . $tahun,
                        'bulan_bill' => $bulan->bulan_bill,
                        'bulan_id' => $bulan->bulan_id,
                        'period' => $period ? $period->period_id : 'Unknown period',
                        'status' => (bool) $bulan->bulan_status,
                        'is_in_cart' => $bulan->flip_status === 'Ready' ? 'Ready' : false
                    ]
                ];
            });

            //dd($formattedData);
            return response()->json([
                'is_correct' => true,
                'message' => 'Data anda valid',
                $formattedData
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

    public function index2(Request $request)
    {
        $token = JWTAuth::parseToken();

        // Get the token payload
        $claims = $token->getPayload();
        $payment = $claims->get('payment');
        // dd($payment);
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
            ->where(function ($query) {
                $query->where('flip_status', '!=', 'LUNAS')
                    ->orWhereNull('flip_status');  // Memastikan flip_status valid
            })
            ->whereHas('payment', function ($query) {
                $query->where('payment_is_batch', '1');  // Batch tidak terproses
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
                    'is_in_cart' => $bulan->flip_status === 'Ready' ? true : false
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
        //  }
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        $setting = Setting::where('setting_name', 'api_secret_key')->first();
        if ($setting) {
            $periodId = $request->query('period_id');
            $result = Bulan::join('batch_item as bi', 'bi.batch_item_payment_id', '=', 'bulan.payment_payment_id')
                ->join('batchpayment as bp', 'bp.id', '=', 'bi.batch_item_batchpayment_id')
                ->join('month as m', 'm.month_id', '=', 'bulan.month_month_id')
                ->join('payment as p', 'p.payment_id', '=', 'bulan.payment_payment_id') // Tambahkan join ke tabel payment
                ->join('period as per', 'per.period_id', '=', 'p.period_period_id')
                ->select(
                    'bp.name as nama_paket',
                    'bp.id as id_paket',
                    'bulan.bulan_id',
                    'm.month_id',
                    'm.month_name',
                    'p.period_period_id',
                    'bulan.flip_status',
                    'per.period_start', // Pilih period_start dari tabel period
                    'per.period_end',   // Pilih period_end dari tabel period
                    DB::raw('SUM(bulan.bulan_bill) as total_bulan')
                )
                ->where('bulan.student_student_id', $user->student_id)
                ->where('bulan.bulan_status', 0)
                ->where('p.payment_is_batch', '1') // Tambahkan kondisi payment_is_batch
                ->orderBy('m.month_id', 'ASC');
            if ($periodId) {
                $result->where('p.period_period_id', $periodId);
            }

            $result = $result
                ->groupBy('bp.name', 'bp.id', 'bulan.bulan_id', 'm.month_name', 'm.month_id', 'bulan.flip_status', 'p.period_period_id', 'per.period_start', 'per.period_end',)
                ->get();


            // $status = $result->flip_status;
            //dd($status);
            $groupedResult = $result->groupBy('id_paket')->map(function ($paket) {

                $totalNominal = $paket->sum('total_bulan');

                $detail = $paket->groupBy('month_name')->map(function ($monthGroup) {
                    //dd($monthGroup->flip_status);
                    // $dataBulan = Bulan::where('student_student_id', $userId)->where('bulan_id', $monthGroup->pluck('bulan_id')->toArray()[0])->first();

                    return [
                        'month_name' => $monthGroup->first()->month_name,
                        'nominal' => $monthGroup->sum('total_bulan'),
                        'bulan_ids' => $monthGroup->pluck('bulan_id')->toArray(),
                        'is_in_cart' => $monthGroup->first()->flip_status === 'READY' ? true : false
                        // 'period_period_id' => $monthGroup->first()->period_period_id,
                    ];
                });

                return [
                    'nama_paket' => $paket->first()->nama_paket . ' ' . $paket->first()->period_start . '/' . $paket->first()->period_end,
                    'id_paket' => $paket->first()->id_paket,
                    'nominal' => $totalNominal,
                    'detail' => $detail->values()->toArray(),
                    //    ]

                ];
            })->values();

            //   if ($groupedResult) {
            return response()->json([
                'is_correct' => true,
                'message' => 'data anda valid',
                'paket' => $groupedResult
            ], 200);
        } else {
            $periodId = $request->query('period_id');
            $result = Bulan::join('batch_item as bi', 'bi.batch_item_payment_id', '=', 'bulan.payment_payment_id')
                ->join('batchpayment as bp', 'bp.id', '=', 'bi.batch_item_batchpayment_id')
                ->join('month as m', 'm.month_id', '=', 'bulan.month_month_id')
                ->join('payment as p', 'p.payment_id', '=', 'bulan.payment_payment_id') // Tambahkan join ke tabel payment
                ->join('period as per', 'per.period_id', '=', 'p.period_period_id')
                ->select(
                    'bp.name as nama_paket',
                    'bp.id as id_paket',
                    'bulan.bulan_id',
                    'm.month_id',
                    'm.month_name',
                    'p.period_period_id',
                    'bulan.ipaymu_status',
                    'per.period_start', // Pilih period_start dari tabel period
                    'per.period_end',   // Pilih period_end dari tabel period
                    DB::raw('SUM(bulan.bulan_bill) as total_bulan')
                )
                ->where('bulan.student_student_id', $user->student_id)
                ->where('bulan.bulan_status', 0)
                ->where('p.payment_is_batch', '1') // Tambahkan kondisi payment_is_batch
                ->orderBy('m.month_id', 'ASC');
            if ($periodId) {
                $result->where('p.period_period_id', $periodId);
            }

            $result = $result
                ->groupBy('bp.name', 'bp.id', 'bulan.bulan_id', 'm.month_name', 'm.month_id', 'bulan.ipaymu_status', 'p.period_period_id', 'per.period_start', 'per.period_end',)
                ->get();


            // $status = $result->flip_status;
            //dd($status);
            $groupedResult = $result->groupBy('id_paket')->map(function ($paket) {

                $totalNominal = $paket->sum('total_bulan');

                $detail = $paket->groupBy('month_name')->map(function ($monthGroup) {
                    //dd($monthGroup->flip_status);
                    // $dataBulan = Bulan::where('student_student_id', $userId)->where('bulan_id', $monthGroup->pluck('bulan_id')->toArray()[0])->first();

                    return [
                        'month_name' => $monthGroup->first()->month_name,
                        'nominal' => $monthGroup->sum('total_bulan'),
                        'bulan_ids' => $monthGroup->pluck('bulan_id')->toArray(),
                        'is_in_cart' => $monthGroup->first()->ipaymu_status === 'READY' ? true : false
                        // 'period_period_id' => $monthGroup->first()->period_period_id,
                    ];
                });

                return [
                    'nama_paket' => $paket->first()->nama_paket . ' ' . $paket->first()->period_start . '/' . $paket->first()->period_end,
                    'id_paket' => $paket->first()->id_paket,
                    'nominal' => $totalNominal,
                    'detail' => $detail->values()->toArray(),
                    //    ]

                ];
            })->values();

            //   if ($groupedResult) {
            return response()->json([
                'is_correct' => true,
                'message' => 'data anda valid',
                'paket' => $groupedResult
            ], 200);
        }
    }

    public function lunas(Request $request)
    {
        $setting = Setting::where('setting_name', 'api_secret_key')->first();
        if ($setting) {
            $user = auth()->user();
            $periodId = $request->query('period_id');
            $result = Bulan::join('batch_item as bi', 'bi.batch_item_payment_id', '=', 'bulan.payment_payment_id')
                ->join('batchpayment as bp', 'bp.id', '=', 'bi.batch_item_batchpayment_id')
                ->join('month as m', 'm.month_id', '=', 'bulan.month_month_id')
                ->join('payment as p', 'p.payment_id', '=', 'bulan.payment_payment_id') // Tambahkan join ke tabel payment
                ->join('period as per', 'per.period_id', '=', 'p.period_period_id')
                ->join('pos as pos', 'pos.pos_id', '=', 'p.pos_pos_id')
                ->select(
                    'bp.name as nama_paket',
                    'bp.id as id_paket',
                    'bulan.bulan_id',
                    'm.month_id',
                    'm.month_name',
                    'p.period_period_id',
                    'bulan.bulan_bill',
                    'bulan.flip_status',
                    'per.period_start', // Pilih period_start dari tabel period
                    'per.period_end',   // Pilih period_end dari tabel period
                    'pos.pos_name',
                    DB::raw('SUM(bulan.bulan_bill) as total_bulan')
                )
                ->where('bulan.student_student_id', $user->student_id)
                ->where('bulan.bulan_status', 1)
                ->where('flip_status', 'LUNAS')
                ->where('p.payment_is_batch', '1') // Tambahkan kondisi payment_is_batch
                ->orderBy('m.month_id', 'ASC');
            if ($periodId) {
                $result->where('p.period_period_id', $periodId);
            }

            $result = $result
                ->groupBy('bp.name', 'bp.id', 'bulan.bulan_id', 'm.month_name', 'm.month_id', 'bulan.flip_status', 'p.period_period_id', 'bulan.bulan_bill', 'per.period_start', 'per.period_end', 'pos.pos_name')
                ->get();


            // $status = $result->flip_status;
            //dd($status);
            $groupedResult = $result->groupBy('id_paket')->map(function ($paket) {

                $totalNominal = $paket->sum('total_bulan');

                $detail = $paket->groupBy('month_name')->map(function ($monthGroup) use ($paket) {
                    //dd($monthGroup->flip_status);
                    // $dataBulan = Bulan::where('student_student_id', $userId)->where('bulan_id', $monthGroup->pluck('bulan_id')->toArray()[0])->first();

                    return [
                        'month_name' => $paket->first()->nama_paket . ' ' . $monthGroup->first()->month_name,
                        'nominal' => $monthGroup->sum('total_bulan'),
                        'bulan_list' =>  $monthGroup->map(function ($bulan) {
                            return [
                                'bulan_id' => $bulan->bulan_id,
                                'name' => $bulan->pos_name . ' ' . $bulan->month_name,  // Get pos_name from the relation
                                'nominal' => (int) $bulan->bulan_bill,  // Use bulan_bill as the nominal
                            ];
                        })->toArray(),
                        //'is_in_cart' => $monthGroup->first()->flip_status === 'Ready' ? true : false
                        // 'period_period_id' => $monthGroup->first()->period_period_id,
                    ];
                });

                return [
                    'id_paket' => $paket->first()->id_paket,
                    'nama_paket' => $paket->first()->nama_paket . ' ' . $paket->first()->period_start . '/' . $paket->first()->period_end,
                    //'nominal' => $totalNominal,
                    'daftar_riwayat' => $detail->values()->toArray(),
                    //    ]

                ];
            })->values();

            //   if ($groupedResult) {
            return response()->json([
                'is_correct' => true,
                'message' => 'data anda valid',
                'paket_history' => $groupedResult
            ], 200);
        } else {
            $user = auth()->user();
            $periodId = $request->query('period_id');
            $result = Bulan::join('batch_item as bi', 'bi.batch_item_payment_id', '=', 'bulan.payment_payment_id')
                ->join('batchpayment as bp', 'bp.id', '=', 'bi.batch_item_batchpayment_id')
                ->join('month as m', 'm.month_id', '=', 'bulan.month_month_id')
                ->join('payment as p', 'p.payment_id', '=', 'bulan.payment_payment_id') // Tambahkan join ke tabel payment
                ->join('period as per', 'per.period_id', '=', 'p.period_period_id')
                ->join('pos as pos', 'pos.pos_id', '=', 'p.pos_pos_id')
                ->select(
                    'bp.name as nama_paket',
                    'bp.id as id_paket',
                    'bulan.bulan_id',
                    'm.month_id',
                    'm.month_name',
                    'p.period_period_id',
                    'bulan.bulan_bill',
                    'bulan.ipaymu_status',
                    'per.period_start', // Pilih period_start dari tabel period
                    'per.period_end',   // Pilih period_end dari tabel period
                    'pos.pos_name',
                    DB::raw('SUM(bulan.bulan_bill) as total_bulan')
                )
                ->where('bulan.student_student_id', $user->student_id)
                ->where('bulan.bulan_status', 1)
                ->where('ipaymu_status', 'LUNAS')
                ->where('p.payment_is_batch', '1') // Tambahkan kondisi payment_is_batch
                ->orderBy('m.month_id', 'ASC');
            if ($periodId) {
                $result->where('p.period_period_id', $periodId);
            }

            $result = $result
                ->groupBy('bp.name', 'bp.id', 'bulan.bulan_id', 'm.month_name', 'm.month_id', 'bulan.ipaymu_status', 'p.period_period_id', 'bulan.bulan_bill', 'per.period_start', 'per.period_end', 'pos.pos_name')
                ->get();


            // $status = $result->flip_status;
            //dd($status);
            $groupedResult = $result->groupBy('id_paket')->map(function ($paket) {

                $totalNominal = $paket->sum('total_bulan');

                $detail = $paket->groupBy('month_name')->map(function ($monthGroup) use ($paket) {
                    //dd($monthGroup->flip_status);
                    // $dataBulan = Bulan::where('student_student_id', $userId)->where('bulan_id', $monthGroup->pluck('bulan_id')->toArray()[0])->first();

                    return [
                        'month_name' => $paket->first()->nama_paket . ' ' . $monthGroup->first()->month_name,
                        'nominal' => $monthGroup->sum('total_bulan'),
                        'bulan_list' =>  $monthGroup->map(function ($bulan) {
                            return [
                                'bulan_id' => $bulan->bulan_id,
                                'name' => $bulan->pos_name . ' ' . $bulan->month_name,  // Get pos_name from the relation
                                'nominal' => (int) $bulan->bulan_bill,  // Use bulan_bill as the nominal
                            ];
                        })->toArray(),
                        //'is_in_cart' => $monthGroup->first()->flip_status === 'Ready' ? true : false
                        // 'period_period_id' => $monthGroup->first()->period_period_id,
                    ];
                });

                return [
                    'id_paket' => $paket->first()->id_paket,
                    'nama_paket' => $paket->first()->nama_paket . ' ' . $paket->first()->period_start . '/' . $paket->first()->period_end,
                    //'nominal' => $totalNominal,
                    'daftar_riwayat' => $detail->values()->toArray(),
                    //    ]

                ];
            })->values();

            //   if ($groupedResult) {
            return response()->json([
                'is_correct' => true,
                'message' => 'data anda valid',
                'paket_history' => $groupedResult
            ], 200);
        }
    }

    public function update(Request $request)
    {
        $setting = Setting::where('setting_name', 'api_secret_key')->first();

        if ($setting) {
            $ids = $request->input('id');

            if (!is_array($ids)) {
                return response()->json([
                    'is_correct' => false,
                    'message' => 'ID harus berupa array.'
                ], 400);
            }

            // Ambil semua data Bulan yang sesuai dengan array ID
            $bulanRecords = Bulan::whereIn('bulan_id', $ids)->get();

            if ($bulanRecords->isEmpty()) {
                return response()->json([
                    'is_correct' => false,
                    'message' => 'Data tidak ditemukan.'
                ], 404);
            }

            // Iterasi dan perbarui flip_status masing-masing
            foreach ($bulanRecords as $bulan) {
                $newStatus = $bulan->flip_status === 'READY' ? null : 'READY';
                $bulan->update(['flip_status' => $newStatus]);
            }

            return response()->json([
                'is_correct' => true,
                'message' => 'Status berhasil diperbarui.',
                'updated_ids' => $ids
            ]);
        } else {
            $ids = $request->input('id');

            if (!is_array($ids)) {
                return response()->json([
                    'is_correct' => false,
                    'message' => 'ID harus berupa array.'
                ], 400);
            }

            // Ambil semua data Bulan yang sesuai dengan array ID
            $bulanRecords = Bulan::whereIn('bulan_id', $ids)->get();

            if ($bulanRecords->isEmpty()) {
                return response()->json([
                    'is_correct' => false,
                    'message' => 'Data tidak ditemukan.'
                ], 404);
            }

            // Iterasi dan perbarui flip_status masing-masing
            foreach ($bulanRecords as $bulan) {
                $newStatus = $bulan->ipaymu_status === 'READY' ? null : 'READY';
                $bulan->update(['ipaymu_status' => $newStatus]);
            }

            return response()->json([
                'is_correct' => true,
                'message' => 'Status berhasil diperbarui.',
                'updated_ids' => $ids
            ]);
        }
    }  // Pastikan `id` adalah array

}
