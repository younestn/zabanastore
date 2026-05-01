<?php

namespace App\Http\Controllers\RestAPI\v3\seller;

use App\Http\Controllers\Controller;
use App\Services\Shipping\ShippingCarrierManager;
use App\Utils\Helpers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ShippingCarrierController extends Controller
{
    public function __construct(
        private readonly ShippingCarrierManager $shippingCarrierManager,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $seller = $request->seller;

        return response()->json([
            'carriers' => $this->shippingCarrierManager->listVendorCarriers((int)$seller['id']),
        ], 200);
    }

    public function settings(Request $request): JsonResponse
    {
        return $this->index($request);
    }

    public function show(Request $request, string $carrier): JsonResponse
    {
        $seller = $request->seller;
        $carrierKey = strtolower($carrier);

        $carrier = collect($this->shippingCarrierManager->listVendorCarriers((int)$seller['id']))
            ->firstWhere('carrier_key', $carrierKey);

        if (!$carrier) {
            return response()->json(['message' => translate('data_not_found')], 404);
        }

        return response()->json($carrier, 200);
    }

    public function store(Request $request, string $carrier): JsonResponse
    {
        $seller = $request->seller;
        $carrierKey = strtolower($carrier);
        $carrierDefinition = $this->shippingCarrierManager->getCarrierDefinition($carrierKey);

        if (!$carrierDefinition) {
            return response()->json(['message' => translate('data_not_found')], 404);
        }

        $validationRules = ['status' => 'nullable|in:0,1'];
        foreach ($carrierDefinition['credential_fields'] ?? [] as $field) {
            $validationRules[$field['key']] = 'nullable|string|max:500';
        }

        $validator = Validator::make($request->all(), $validationRules);
        if ($validator->errors()->count() > 0) {
            return response()->json(['errors' => Helpers::validationErrorProcessor($validator)], 422);
        }

        $this->shippingCarrierManager->saveVendorCarrierSettings((int)$seller['id'], $carrierKey, $request->all());

        return response()->json([
            'message' => translate('successfully_updated'),
            'carrier' => collect($this->shippingCarrierManager->listVendorCarriers((int)$seller['id']))
                ->firstWhere('carrier_key', $carrierKey),
        ], 200);
    }

    public function testConnection(Request $request, string $carrier): JsonResponse
    {
        $seller = $request->seller;
        $carrierKey = strtolower($carrier);
        $carrierDefinition = $this->shippingCarrierManager->getCarrierDefinition($carrierKey);

        if (!$carrierDefinition) {
            return response()->json(['message' => translate('data_not_found')], 404);
        }

        $validationRules = [];
        foreach ($carrierDefinition['credential_fields'] ?? [] as $field) {
            $validationRules[$field['key']] = 'nullable|string|max:500';
        }

        $validator = Validator::make($request->all(), $validationRules);
        if ($validator->errors()->count() > 0) {
            return response()->json(['errors' => Helpers::validationErrorProcessor($validator)], 422);
        }

        $result = $this->shippingCarrierManager->testVendorCarrierConnection((int)$seller['id'], $carrierKey, $request->all());

        return response()->json([
            'success' => (int)($result['success'] ?? 0),
            'message' => $result['message'] ?? null,
            'carrier' => collect($this->shippingCarrierManager->listVendorCarriers((int)$seller['id']))
                ->firstWhere('carrier_key', $carrierKey),
        ], ($result['success'] ?? false) ? 200 : 422);
    }

    public function toggle(Request $request, string $carrier): JsonResponse
    {
        $seller = $request->seller;
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:0,1',
        ]);

        if ($validator->errors()->count() > 0) {
            return response()->json(['errors' => Helpers::validationErrorProcessor($validator)], 422);
        }

        $record = $this->shippingCarrierManager->toggleVendorCarrier(
            (int)$seller['id'],
            strtolower($carrier),
            (bool)$request->input('status')
        );

        return response()->json([
            'message' => translate('successfully_status_updated'),
            'carrier' => collect($this->shippingCarrierManager->listVendorCarriers((int)$seller['id']))
                ->firstWhere('carrier_key', strtolower((string)($record->carrier_key ?: $record->name))),
        ], 200);
    }
}
