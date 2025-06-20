<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\FlipChannel;
use App\Models\data_ipaymu_channel;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class FlipChannelController extends Controller
{
    public function index()
    {
        $token = JWTAuth::parseToken();
        // Get the token payload
        $claims = $token->getPayload();
        $payment = $claims->get('payment');

        //cek pakai ipaymu apa tidak
        $setting = Setting::where('setting_name', 'api_secret_key')->first();
        //  dd($setting);
        if ($setting) {
            $data = FlipChannel::all()->map(function ($item) {
                return [
                    'metode' => $item->payment_channel,
                    'bank' => $item->bank,
                    'logo' => 'https://mobile.epesantren.co.id/walsan/assets/logo_bank/' . $item->logo,
                    'kode' => $item->kode,
                    'fee' => $item->fee
                ];
            });

            // if ($data) {
            return response()->json([
                'is_correct' => true,
                'message' => 'success',
                'payment' => $payment,
                'data' => $data
            ], 200);
        } else {
            //  dd('coba');
            $data = data_ipaymu_channel::whereNot('payment_channel', 'h2h|bmi')->get()->map(function ($item) {
                return [
                    'metode' => $item->payment_channel,
                    'bank' => $item->bank,
                    'logo' => 'https://mobile.epesantren.co.id/walsan/assets/logo_bank/' . $item->logo,
                    'kode' => $item->kode,
                    'fee' => $item->fee
                ];
            });

            // if ($data) {
            return response()->json([
                'is_correct' => true,
                'message' => 'success',
                'payment' => $payment,
                'data' => $data
            ], 200);
        }
    }
}
