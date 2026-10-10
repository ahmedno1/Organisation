<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class UserSessionController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->to(route('activity_logs.index').'#sessions-section');
    }
}
