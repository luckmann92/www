<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class KioskController extends Controller
{
    public function index(Request $request)
    {
        if ($request->get('mode') !== 'demo') {
            die();
        }
        return Inertia::render('Kiosk');
    }
}
