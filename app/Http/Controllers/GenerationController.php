<?php

namespace App\Http\Controllers;

use App\Models\Generation;
use Illuminate\Http\JsonResponse;

class GenerationController extends BaseController
{
    public function show(Generation $generation): JsonResponse
    {
        abort_unless($generation->user_id === auth()->id(), 404);

        $generation->failIfStale();

        return response()->json([
            'status' => $generation->status,
        ]);
    }
}
