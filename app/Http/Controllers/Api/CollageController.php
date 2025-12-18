<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Collage;
use Orchid\Attachment\Models\Attachment;

class CollageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    /**
     * Return a list of active collages.
     *
     * Each item includes:
     *   - id
     *   - title
     *   - preview_path (URL)
     *   - price
     */
    public function index()
    {
        $collages = Collage::where('is_active', true)
            ->select('id', 'title', 'preview_path', 'price')
            ->get();

        // Convert storage path to public URL (assuming storage:link is set)
        $collages->each(function ($collage) {
            if (is_array($collage->preview_path)) {
                foreach($collage->preview_path as $i => $attachmentId) {
                    if ($i > 0) {
                        continue;
                    }
                    $attachment = Attachment::find($attachmentId);
                    $collage->preview_url = $attachment->url();
                }
            }
            unset($collage->preview_path);
        });

        return response()->json($collages);
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
    /**
     * Show a single collage (active only).
     *
     * Returns the collage data with a public preview URL.
     */
    public function show(string $id)
    {
        $collage = Collage::where('is_active', true)
            ->where('id', $id)
            ->firstOrFail()
            ->makeHidden(['preview_path']);

        if (is_array($collage->preview_path)) {
            // Если preview_path является массивом, используем первый элемент
            $path = !empty($collage->preview_path) ? $collage->preview_path[0] : null;
        } else {
            // Если это строка, используем как есть
            $path = $collage->preview_path;
        }

        if ($path) {
            $collage->preview_url = \Illuminate\Support\Facades\Storage::url($path);
        } else {
            $collage->preview_url = null;
        }

        return response()->json($collage);
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
