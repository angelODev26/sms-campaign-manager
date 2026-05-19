<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessCampaignCsv;
use App\Models\Campaign;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    public function index(Request $request)
    {
        $campaigns = $request->user()->campaigns()->latest()->get();
        return response()->json($campaigns);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'         => 'required|string|max:255',
            'message'      => 'required|string',
            'scheduled_at' => 'required|date|after:now',
            'csv_file'     => 'required|file|mimes:csv,txt|max:51200', // max 50MB
        ]);

        $file      = $request->file('csv_file');
        $content   = file_get_contents($file->getRealPath());
        $firstLine = strtok($content, "\n");

        // Detectar separador
        $separator = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

        // Validar que tenga columna phone
        $headers = str_getcsv($firstLine, $separator);
        $headers = array_map('trim', array_map('strtolower', $headers));

        if (!in_array('phone', $headers)) {
            return response()->json([
                'message' => 'El archivo CSV debe tener una columna llamada "phone"'
            ], 422);
        }

        $campaign = $request->user()->campaigns()->create([
            'name'         => $request->name,
            'message'      => $request->message,
            'status'       => 'draft',
            'scheduled_at' => $request->scheduled_at,
        ]);

        $path = $file->store('csv_uploads');

        ProcessCampaignCsv::dispatch($campaign, storage_path('app/private/' . $path), $separator);

        return response()->json($campaign, 201);
    }

    public function show(Request $request, Campaign $campaign)
    {
        if ($campaign->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($campaign->load('details'));
    }

    public function destroy(Request $request, Campaign $campaign)
    {
        if ($campaign->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $campaign->delete();
        return response()->json(['message' => 'Campaign deleted']);
    }
}
