<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\LeadResource;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LeadController extends Controller
{
    /**
     * Retrieve all leads assigned to the authenticated user.
     */
    public function assigned(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $leads = Lead::where('assigned_to_id', $user->id)
            ->with('lastCall')
            ->orderByDesc('assigned_at')
            ->get();

        return LeadResource::collection($leads);
    }
}
