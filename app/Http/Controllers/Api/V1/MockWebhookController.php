<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MockWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        Log::info('Mock webhook received callback payload:', $request->all());

        return response()->json([
            'success' => true,
            'message' => 'Callback received successfully.',
            'received' => $request->all(),
        ], 200);
    }
}
