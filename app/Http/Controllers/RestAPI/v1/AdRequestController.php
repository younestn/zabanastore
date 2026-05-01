<?php

namespace App\Http\Controllers\RestAPI\v1;

use App\Http\Controllers\Controller;
use App\Services\AdRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdRequestController extends Controller
{
    public function __construct(
        private readonly AdRequestService $adRequestService,
    ) {
    }

    public function active(Request $request): JsonResponse
    {
        $placement = $request->query('placement');
        $limit = max(1, min((int) $request->query('limit', 10), 50));

        $adRequests = $this->adRequestService
            ->getActiveAdsQuery($placement)
            ->limit($limit)
            ->get();

        return response()->json(
            $adRequests->map(fn ($adRequest) => $this->adRequestService->formatActiveAd($adRequest))->values(),
            200
        );
    }

    public function impression(Request $request, int $id): JsonResponse
    {
        return $this->track($request, $id, 'impression');
    }

    public function click(Request $request, int $id): JsonResponse
    {
        return $this->track($request, $id, 'click');
    }

    private function track(Request $request, int $id, string $eventType): JsonResponse
    {
        $validated = $request->validate([
            'source' => ['required', 'in:app,web'],
        ]);

        $tracked = $this->adRequestService->trackActiveAdEvent($id, $validated['source'], $eventType);

        if (!$tracked) {
            return response()->json([
                'success' => false,
                'message' => translate('not_found'),
            ], 404);
        }

        return response()->json(['success' => true], 200);
    }
}
