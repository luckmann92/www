<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Photo;
use App\Models\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PhotoController extends Controller
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
    /**
     * Upload a new photo for a session.
     *
     * Expected payload:
     *   - session_token (string) – ID of the session
     *   - photo (string) – Base64‑encoded JPEG/PNG image
     *
     * Returns:
     *   - preview_url (string) – URL to the stored original image
     */
    public function upload(Request $request)
    {
        $request->validate([
            'session_token' => 'required|exists:sessions,id',
            'photo' => ['required', 'string'],
        ]);

        // Find the session
        $session = Session::findOrFail($request->input('session_token'));

        // Decode Base64 image
        $base64 = $request->input('photo');
        // Remove data URI prefix if present
        if (Str::contains($base64, ';base64,')) {
            $base64 = explode(';base64,', $base64, 2)[1];
        }
        $imageData = base64_decode($base64);
        if ($imageData === false) {
            return response()->json(['error' => 'Invalid Base64 image data'], 422);
        }

        // Generate a unique filename
        $extension = 'jpg'; // default; could be detected via mime if needed
        $filename = Str::uuid() . '.' . $extension;

        // Store the file
        $path = 'photos/originals/' . $filename;
        Storage::disk('local')->put($path, $imageData);

        // Create Photo record
        $photo = Photo::create([
            'session_id' => $session->id,
            'type' => 'original',
            'path' => $path,
            'blur_level' => 0,
            'status' => 'uploaded',
        ]);

        // Generate a public URL (assuming `php artisan storage:link` is set)
        $previewUrl = Storage::url($path);

        return response()->json([
            'photo_id' => $photo->id,
            'preview_url' => $previewUrl,
        ]);
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
}
