<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Digiflazz\TransactionPlnDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Digiflazz\TransactionPlnRequest;
use App\Services\Digiflazz\PlnPaymentService;
use App\Services\Monitoring\ApiMetricsCollector;
use Illuminate\Http\JsonResponse;

class PlnTransactionController extends Controller
{
    public function __construct(
        protected PlnPaymentService $paymentService,
        protected ApiMetricsCollector $metricsCollector
    ) {}

    /**
     * Handle PLN payment / transaction request.
     */
    public function pay(TransactionPlnRequest $request): JsonResponse
    {
        $dto = TransactionPlnDTO::fromRequest($request->validated());
        $response = $this->paymentService->pay($dto);

        $status = $response['data']['status'] ?? 'Gagal';
        $this->metricsCollector->recordPayment($status);

        return response()->json($response, 200);
    }
}
