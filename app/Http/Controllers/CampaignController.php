<?php

namespace App\Http\Controllers;

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
            'scheduled_at' => 'nullable|date',
        ]);

        $campaign = $request->user()->campaigns()->create([
            'name'         => $request->name,
            'message'      => $request->message,
            'status'       => 'draft',
            'scheduled_at' => $request->scheduled_at,
        ]);

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
