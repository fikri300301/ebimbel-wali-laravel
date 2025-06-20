<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function index()
    {
        return view('login');
    }
    public function authenticate(Request $request)
    {
        // Validasi input
        $credentials = $request->validate([
            'user_email' => ['required'],
            'user_password' => ['required'],
        ]);

        // Mencoba autentikasi dengan md5 pada password
        $user = User::where('user_email', $request->user_email)
            ->where('user_password', md5($request->user_password))
            ->first();

        if ($user) {
            // Login manual tanpa Auth::attempt
            Auth::guard('web')->login($user);

            return redirect()->intended('/api/documentation#/');
        }

        // Jika gagal login, kembali dengan error
        return back()->withErrors(['error' => 'Invalid credentials'])->withInput();
    }
}
