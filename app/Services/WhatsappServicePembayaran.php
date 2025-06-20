<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class WhatsappServicePembayaran
{
    protected $apiKey;
    protected $sender;
    protected $url;

    public function __construct()
    {
        // $this->apiKey = env('WA_API_KEY', 'default_api_key');
        // $this->sender = env('WA_SENDER', 'default_sender_number');
        // dd($this->apiKey, $this->sender);
        $this->sender = DB::table('setting')->where('setting_name', 'setting_wa_center')->value('setting_value');
        //dd($this->apiKey);
        $this->apiKey = DB::table('setting')->where('setting_name', 'setting_wa_key')->value('setting_value');
        // dd($this->sender, $this->apiKey);
        $this->url = 'https://indoweb.notifwa.com/send-message';
    }

    public function kirimPesan($noWa, $pesan)
    {
        $noSend = str_replace('+', '', $noWa);
        // dd($noSend);
        $data = [
            'api_key' => $this->apiKey,
            'sender' => $this->sender,
            'number' => $noSend,
            'message' => $pesan,
        ];

        $dataString = json_encode($data);

        $ch = curl_init($this->url);

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $dataString);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_VERBOSE, 0);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($dataString)
            )
        );

        $result = curl_exec($ch);
        curl_close($ch);

        return $result;
    }
}
