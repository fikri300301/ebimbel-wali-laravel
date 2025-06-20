<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

class HubunganKamiController extends Controller
{
    public function index()
    {
        $noWa = Setting::where('setting_name', 'setting_wa_center')->first();
        $noTelegram = Setting::where('setting_name', 'setting_telegram')->first();
        //   dd($data);
        return response()->json(
            [
                'status' => 'success',
                'message' => 'Data berhasil diambil',
                'nowa' => $noWa->setting_value,
                'noTelegram' => $noTelegram->setting_value
            ],
            200
        );
    }
}
