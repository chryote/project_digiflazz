<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Auth\LoginDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Auth\AuthService;
use App\Services\Monitoring\ApiMetricsCollector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        protected AuthService $authService,
        protected ApiMetricsCollector $metricsCollector
    ) {}

    /**
     * Authenticate merchant and issue API bearer token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $dto = LoginDTO::fromRequest($request->validated());
            $result = $this->authService->login($dto);

            $this->metricsCollector->recordLoginAttempt(true);

            return response()->json([
                'success' => true,
                'message' => 'Login berhasil.',
                'data' => $result,
            ], 200);
        } catch (ValidationException $e) {
            $this->metricsCollector->recordLoginAttempt(false);

            return response()->json([
                'success' => false,
                'message' => 'Login gagal.',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Get current authenticated merchant profile.
     */
    public function me(Request $request): JsonResponse
    {
        $profile = $this->authService->profile($request->user());

        return response()->json([
            'success' => true,
            'data' => $profile,
        ], 200);
    }

    /**
     * Log the merchant out by revoking current bearer token.
     */
    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil. Token telah dicabut.',
        ], 200);
    }

    /**
     * Regenerate Digiflazz API key.
     */
    public function regenerateApiKey(Request $request): JsonResponse
    {
        $newKey = $this->authService->regenerateApiKey($request->user());

        return response()->json([
            'success' => true,
            'message' => 'API Key berhasil diperbarui.',
            'data' => [
                'api_key' => $newKey,
            ],
        ], 200);
    }
}
