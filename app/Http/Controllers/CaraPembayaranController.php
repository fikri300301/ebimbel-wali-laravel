<?php

namespace App\Http\Controllers;

use App\Models\FlipChannel;
use Illuminate\Http\Request;

class CaraPembayaranController extends Controller
{
    public function index()
    {
        $data = FlipChannel::all();
        return response()->json([
            'is_correct' => true,
            'message' => 'success',
            'flip_channel' => $data
        ]);
    }
}
