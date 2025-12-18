<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Session;

class SessionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    /**
     * Start a new session.
     */
    public function start(Request $request)
    {
        $sessionId = uniqid('sess_', true);

        $session = Session::create([
            'id' => $sessionId,
            'device_id' => $request->input('device_id'),
            'status' => 'active',
            'started_at' => now(),
            'payload' => '',
            'last_activity' => time(),
        ]);

        return response()->json(['session_token' => $session->id]);
    }

    /**
     * End an existing session.
     */
    public function end(Request $request)
    {
        $session = Session::findOrFail($request->input('session_id'));
        $session->status = 'finished';
        $session->finished_at = now();
        $session->save();

        return response()->json(['message' => 'Session ended']);
    }
}
