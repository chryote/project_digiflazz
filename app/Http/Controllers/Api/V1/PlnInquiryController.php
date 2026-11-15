<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Digiflazz\InquiryPlnDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Digiflazz\InquiryPlnRequest;
use App\Services\Digiflazz\PlnInquiryService;
use App\Services\Monitoring\ApiMetricsCollector;
use Illuminate\Http\JsonResponse;

class PlnInquiryController extends Controller
{
    public function __construct(
        protected PlnInquiryService $inquiryService,
        protected ApiMetricsCollector $metricsCollector
    ) {}

    /**
     * Handle PLN inquiry request.
     */
    public function inquire(InquiryPlnRequest $request): JsonResponse
    {
        $dto = InquiryPlnDTO::fromRequest($request->validated());
        $response = $this->inquiryService->inquire($dto);

        $status = $response['data']['status'] ?? 'Gagal';
        $this->metricsCollector->recordInquiry($status);

        return response()->json($response, 200);
    }
}
