<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('signin');
    }

    public function login(Request $request)
    {
        return redirect()->route('index');
    }

    public function logout()
    {
        return redirect()->route('signin');
    }
}
