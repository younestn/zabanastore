<?php

namespace App\Http\Controllers\RestAPI\v1;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartShipping;
use App\Models\ShippingMethod;
use App\Models\ShippingType;
use App\Models\Wilaya;
use App\Utils\CartManager;
use App\Utils\Helpers;
use App\Utils\OrderManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ShippingMethodController extends Controller
{
    public function get_shipping_method_info($id)
    {
        try {
            $shipping = ShippingMethod::find($id);
            return response()->json($shipping, 200);
        } catch (\Exception $e) {
            return response()->json(['errors' => $e], 403);
        }
    }

    public function shipping_methods_by_seller(Request $request, $id, $seller_is)
    {
        $seller_is = $seller_is == 'admin' ? 'admin' : 'seller';
        $vendorId = $seller_is == 'admin' ? 0 : (int) $id;

        $defaultMethods = Helpers::getShippingMethods($id, $seller_is)->map(function ($item) {
            return [
                'id' => (int) $item->id,
                'creator_id' => (int) ($item->creator_id ?? 0),
                'creator_type' => (string) $item->creator_type,
                'title' => (string) ($item->title ?? ''),
                'cost' => (double) ($item->cost ?? 0),
                'duration' => (string) ($item->duration ?? ''),
                'status' => (int) ($item->status ?? 0),
                'created_at' => optional($item->created_at)->toJSON(),
                'updated_at' => optional($item->updated_at)->toJSON(),

                'provider_name' => (string) ($item->title ?? ''),
                'service_key' => 'shipping_method',
                'logo' => null,
                'delivery_type' => 'home_delivery',
                'delivery_type_label' => 'Home Delivery',
                'company_name' => (string) ($item->title ?? ''),
                'estimated_days' => (string) ($item->duration ?? ''),
                'is_third_party' => 0,
                'is_noest' => 0,
                'station_code' => null,
            ];
        })->values();

        $customMethods = collect();

        $noest = DB::table('vendor_shipping_companies')
            ->where('vendor_id', $vendorId)
            ->whereRaw('LOWER(name) = ?', ['noest'])
            ->where('status', 1)
            ->first();

        if ($noest && !empty($noest->api_token) && !empty($noest->noest_guid)) {
            $baseId = 800000 + (((int) $noest->id) * 10);

            $customMethods->push([
                'id' => $baseId + 1,
                'creator_id' => $vendorId,
                'creator_type' => $seller_is,
                'title' => 'Noest Home',
                'cost' => (double) ($noest->home_delivery_price ?? 0),
                'duration' => (string) ($noest->delivery_time ?? '24-48h'),
                'status' => 1,
                'created_at' => now()->toJSON(),
                'updated_at' => now()->toJSON(),

                'provider_name' => 'Noest',
                'service_key' => 'noest_home_delivery',
                'logo' => null,
                'delivery_type' => 'home_delivery',
                'delivery_type_label' => 'Home Delivery',
                'company_name' => 'Noest',
                'estimated_days' => (string) ($noest->delivery_time ?? '24-48h'),
                'is_third_party' => 1,
                'is_noest' => 1,
                'station_code' => null,
            ]);

            $customMethods->push([
                'id' => $baseId + 2,
                'creator_id' => $vendorId,
                'creator_type' => $seller_is,
                'title' => 'Noest Desk',
                'cost' => (double) ($noest->desk_delivery_price ?? 0),
                'duration' => (string) ($noest->delivery_time ?? '24-48h'),
                'status' => 1,
                'created_at' => now()->toJSON(),
                'updated_at' => now()->toJSON(),

                'provider_name' => 'Noest',
                'service_key' => 'noest_desk_delivery',
                'logo' => null,
                'delivery_type' => 'desk_delivery',
                'delivery_type_label' => 'Desk Delivery',
                'company_name' => 'Noest',
                'estimated_days' => (string) ($noest->delivery_time ?? '24-48h'),
                'is_third_party' => 1,
                'is_noest' => 1,
                'station_code' => null,
            ]);
        }

        return response()->json(
            $defaultMethods->concat($customMethods)->values(),
            200
        );
    }

    public function noest_wilayas(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'seller_id' => 'nullable|integer',
            'seller_is' => 'nullable|in:admin,seller',
            'cart_group_id' => 'nullable|string',
        ]);

        if ($validator->errors()->count() > 0) {
            return response()->json(['errors' => Helpers::validationErrorProcessor($validator)], 403);
        }

        $vendorNoest = $this->resolveVendorNoestConfig(
            sellerId: $request->input('seller_id'),
            sellerIs: $request->input('seller_is'),
            cartGroupId: $request->input('cart_group_id')
        );

        if (!$vendorNoest || empty($vendorNoest->api_token) || empty($vendorNoest->noest_guid)) {
            return response()->json(['message' => 'NOEST configuration not found'], 422);
        }

        return response()->json(
            Wilaya::query()->orderBy('name')->get(['id', 'code', 'name']),
            200
        );
    }

    public function noest_stations(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'seller_id' => 'nullable|integer',
            'seller_is' => 'nullable|in:admin,seller',
            'cart_group_id' => 'nullable|string',
            'wilaya_id' => 'required|integer|exists:wilayas,id',
        ]);

        if ($validator->errors()->count() > 0) {
            return response()->json(['errors' => Helpers::validationErrorProcessor($validator)], 403);
        }

        $vendorNoest = $this->resolveVendorNoestConfig(
            sellerId: $request->input('seller_id'),
            sellerIs: $request->input('seller_is'),
            cartGroupId: $request->input('cart_group_id')
        );

        if (!$vendorNoest || empty($vendorNoest->api_token)) {
            return response()->json(['message' => 'NOEST configuration not found'], 422);
        }

        $wilaya = Wilaya::find($request->input('wilaya_id'));
        if (!$wilaya) {
            return response()->json(['message' => 'Invalid shipping location'], 422);
        }

        try {
            $response = Http::timeout(15)
                ->acceptJson()
                ->withToken($vendorNoest->api_token)
                ->get('https://app.noest-dz.com/api/public/desks');

            $responseData = $response->json();

            if (!$response->successful() || !is_array($responseData)) {
                return response()->json(['message' => 'Failed to fetch NOEST stations'], 422);
            }

            $noestWilayaCode = (int) ltrim((string) $wilaya->code, '0');

            $desks = collect($responseData)
                ->map(function ($desk, $key) {
                    return [
                        'key' => (string) $key,
                        'code' => (string) ($desk['code'] ?? ''),
                        'name' => (string) ($desk['name'] ?? ''),
                        'address' => (string) ($desk['address'] ?? ''),
                        'phones' => $desk['phones'] ?? [],
                        'email' => (string) ($desk['email'] ?? ''),
                        'wilaya_id' => null,
                    ];
                })
                ->filter(function ($desk) use ($noestWilayaCode) {
                    $stationKeyDigits = (int) preg_replace('/\D/', '', (string) $desk['key']);
                    $stationCodeDigits = (int) preg_replace('/\D/', '', (string) $desk['code']);

                    return $stationKeyDigits === $noestWilayaCode || $stationCodeDigits === $noestWilayaCode;
                })
                ->map(function ($desk) use ($wilaya) {
                    $desk['wilaya_id'] = $wilaya->id;
                    unset($desk['key']);
                    return $desk;
                })
                ->values();

            return response()->json($desks, 200);
        } catch (\Throwable $exception) {
            Log::error('NOEST stations fetch failed from API', [
                'wilaya_id' => $request->input('wilaya_id'),
                'message' => $exception->getMessage(),
            ]);

            return response()->json(['message' => 'Failed to fetch NOEST stations'], 422);
        }
    }

    public function noest_price(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'seller_id' => 'nullable|integer',
            'seller_is' => 'nullable|in:admin,seller',
            'cart_group_id' => 'nullable|string',
            'wilaya_id' => 'required|integer|exists:wilayas,id',
            'delivery_type' => 'required|in:home_delivery,desk_delivery',
            'station_code' => 'nullable|string|max:255',
        ]);

        if ($validator->errors()->count() > 0) {
            return response()->json(['errors' => Helpers::validationErrorProcessor($validator)], 403);
        }

        $vendorNoest = $this->resolveVendorNoestConfig(
            sellerId: $request->input('seller_id'),
            sellerIs: $request->input('seller_is'),
            cartGroupId: $request->input('cart_group_id')
        );

        if (!$vendorNoest || empty($vendorNoest->api_token) || empty($vendorNoest->noest_guid)) {
            return response()->json(['message' => 'NOEST configuration not found'], 422);
        }

        $wilaya = Wilaya::find($request->input('wilaya_id'));
        if (!$wilaya) {
            return response()->json(['message' => 'Invalid shipping location'], 422);
        }

        try {
            $response = Http::timeout(15)
                ->acceptJson()
                ->withToken($vendorNoest->api_token)
                ->get('https://app.noest-dz.com/api/public/fees', [
                    'user_guid' => $vendorNoest->noest_guid,
                ]);

            $responseData = $response->json();

            if (!$response->successful() || !isset($responseData['tarifs']['delivery'])) {
                return response()->json(['message' => 'Failed to calculate NOEST price'], 422);
            }

            $noestWilayaCode = (int) ltrim((string) $wilaya->code, '0');
            $deliveryTarifs = $responseData['tarifs']['delivery'][$noestWilayaCode] ?? null;

            if (!$deliveryTarifs) {
                return response()->json(['message' => 'No NOEST tariff found for this wilaya'], 422);
            }

            $homeDeliveryPrice = (double) ($deliveryTarifs['tarif'] ?? 0);
            $deskDeliveryPrice = (double) ($deliveryTarifs['tarif_stopdesk'] ?? 0);

            $selectedPrice = $request->input('delivery_type') === 'desk_delivery'
                ? $deskDeliveryPrice
                : $homeDeliveryPrice;

            return response()->json([
                'price' => $selectedPrice,
                'currency' => 'DZD',
                'delivery_type' => $request->input('delivery_type'),
                'estimated_days' => (string) ($vendorNoest->delivery_time ?? '24-48h'),
                'home_delivery_price' => $homeDeliveryPrice,
                'desk_delivery_price' => $deskDeliveryPrice,
            ], 200);
        } catch (\Throwable $exception) {
            Log::error('NOEST price fetch failed from API', [
                'wilaya_id' => $request->input('wilaya_id'),
                'message' => $exception->getMessage(),
            ]);

            return response()->json(['message' => 'Failed to calculate NOEST price'], 422);
        }
    }

    public function choose_for_order(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cart_group_id' => 'required',
            'id' => 'required',
            'shipping_cost' => 'nullable|numeric',

            'seller_id' => 'nullable|integer',
            'seller_is' => 'nullable|in:admin,seller',
            'shipping_company' => 'nullable|string|max:255',
            'delivery_type' => 'nullable|in:home_delivery,desk_delivery',
            'wilaya_id' => 'nullable|integer|exists:wilayas,id',
            'wilaya_name' => 'nullable|string|max:255',
            'station_code' => 'nullable|string|max:255',
            'station_name' => 'nullable|string|max:255',
            'baladiya_name' => 'nullable|string|max:255',
            'estimated_days' => 'nullable|string|max:255',
            'is_noest' => 'nullable|in:0,1',
        ], [
            'id.required' => translate('shipping_id_is_required')
        ]);

        if ($validator->errors()->count() > 0) {
            return response()->json(['errors' => Helpers::validationErrorProcessor($validator)], 403);
        }

        $isNoest = (int) $request->input('is_noest', 0) === 1;

        if ($isNoest) {
            if (!$request->filled('delivery_type') || !$request->filled('wilaya_id')) {
                return response()->json([
                    'errors' => [
                        ['code' => 'invalid_noest_payload', 'message' => 'delivery_type and wilaya_id are required for NOEST']
                    ]
                ], 403);
            }

            if (
                $request->input('delivery_type') === 'desk_delivery'
                && !$request->filled('station_code')
            ) {
                return response()->json([
                    'errors' => [
                        ['code' => 'station_code_required', 'message' => 'station_code is required for desk delivery']
                    ]
                ], 403);
            }
        }

        if ($request['cart_group_id'] == 'all_cart_group') {
            foreach (CartManager::get_cart_group_ids(request: $request) as $groupId) {
                $request['cart_group_id'] = $groupId;
                $this->insert_into_cart_shipping($request);
            }
        } else {
            $this->insert_into_cart_shipping($request);
        }

        return response()->json(translate('successfully_added'));
    }

    protected function insert_into_cart_shipping(Request $request): void
    {
        $shipping = CartShipping::where(['cart_group_id' => $request['cart_group_id']])->first();
        if (!$shipping) {
            $shipping = new CartShipping();
        }

        $shippingId = (int) $request['id'];
        $isNoest = (int) $request->input('is_noest', 0) === 1;

        $shipping['cart_group_id'] = $request['cart_group_id'];
        $shipping['shipping_method_id'] = $shippingId;

        $calculatedShippingCost = null;

        if ($isNoest && $request->filled('wilaya_id') && $request->filled('delivery_type')) {
            $wilaya = Wilaya::find($request->input('wilaya_id'));
            $vendorNoest = $this->resolveVendorNoestConfig(
                sellerId: $request->input('seller_id'),
                sellerIs: $request->input('seller_is'),
                cartGroupId: $request->input('cart_group_id')
            );

            if ($wilaya && $vendorNoest && !empty($vendorNoest->api_token) && !empty($vendorNoest->noest_guid)) {
                try {
                    $response = Http::timeout(15)
                        ->acceptJson()
                        ->withToken($vendorNoest->api_token)
                        ->get('https://app.noest-dz.com/api/public/fees', [
                            'user_guid' => $vendorNoest->noest_guid,
                        ]);

                    $responseData = $response->json();

                    if ($response->successful() && isset($responseData['tarifs']['delivery'])) {
                        $noestWilayaCode = (int) ltrim((string) $wilaya->code, '0');
                        $deliveryTarifs = $responseData['tarifs']['delivery'][$noestWilayaCode] ?? null;

                        if ($deliveryTarifs) {
                            $calculatedShippingCost = $request->input('delivery_type') === 'desk_delivery'
                                ? (double) ($deliveryTarifs['tarif_stopdesk'] ?? 0)
                                : (double) ($deliveryTarifs['tarif'] ?? 0);
                        }
                    }
                } catch (\Throwable $exception) {
                    Log::warning('NOEST shipping cost fallback to client payload', [
                        'cart_group_id' => $request->input('cart_group_id'),
                        'message' => $exception->getMessage(),
                    ]);
                }
            }
        }

        if ($calculatedShippingCost !== null) {
            $shipping['shipping_cost'] = $calculatedShippingCost;
        } elseif ($request->filled('shipping_cost')) {
            $shipping['shipping_cost'] = (double) $request['shipping_cost'];
        } else {
            $method = ShippingMethod::find($shippingId);
            $shipping['shipping_cost'] = (double) ($method->cost ?? 0);
        }

        if ($isNoest) {
            $wilaya = $request->filled('wilaya_id') ? Wilaya::find($request->input('wilaya_id')) : null;

            $shipping['extra_data'] = [
                'is_noest' => 1,
                'shipping_company' => $request->input('shipping_company', 'Noest'),
                'delivery_type' => $request->input('delivery_type'),
                'wilaya_id' => $request->filled('wilaya_id') ? (int) $request->input('wilaya_id') : null,
                'wilaya_code' => $wilaya?->code,
                'wilaya_name' => $request->input('wilaya_name') ?: $wilaya?->name,
                'station_code' => $request->input('station_code'),
                'station_name' => $request->input('station_name'),
                'baladiya_name' => $request->input('baladiya_name'),
                'estimated_days' => $request->input('estimated_days'),
            ];
        } else {
            $shipping['extra_data'] = null;
        }

        $shipping->save();
    }

    public function chosen_shipping_methods(Request $request): JsonResponse
    {
        $groupIds = CartManager::get_cart_group_ids(request: $request);
        $cartShipping = CartShipping::whereIn('cart_group_id', $groupIds)->get();

        $cartShipping->map(function ($data) {
            $isCheckedItemExist = Cart::where(['cart_group_id' => $data['cart_group_id'], 'is_checked' => 1])->exists();
            $freeDeliveryStatus = OrderManager::getFreeDeliveryOrderAmountArray($data['cart_group_id'])['status'];
            $data['free_delivery_status'] = $freeDeliveryStatus;
            $data['is_check_item_exist'] = $isCheckedItemExist ? 1 : 0;
            return $data;
        });

        return response()->json($cartShipping, 200);
    }

    public function check_shipping_type(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'seller_is' => 'required',
            'seller_id' => 'required'
        ]);

        if ($validator->errors()->count() > 0) {
            return response()->json(['errors' => Helpers::validationErrorProcessor($validator)]);
        }

        if ($request->seller_is == 'admin') {
            $admin_shipping = ShippingType::where('seller_id', 0)->first();
            $shipping_type = isset($admin_shipping) == true ? $admin_shipping->shipping_type : 'order_wise';
        } else {
            $seller_shipping = ShippingType::where('seller_id', $request->seller_id)->first();
            $shipping_type = isset($seller_shipping) == true ? $seller_shipping->shipping_type : 'order_wise';
        }

        return response()->json(['shipping_type' => $shipping_type], 200);
    }

    private function resolveVendorNoestConfig($sellerId = null, $sellerIs = null, $cartGroupId = null): ?object
    {
        if ((!$sellerId && $sellerId !== 0) || !$sellerIs) {
            if ($cartGroupId) {
                $cartItem = Cart::where('cart_group_id', $cartGroupId)
                    ->where('is_checked', 1)
                    ->first();

                if ($cartItem) {
                    $sellerIs = $cartItem->seller_is;
                    $sellerId = $cartItem->seller_id;
                }
            }
        }

        $sellerIs = $sellerIs === 'admin' ? 'admin' : 'seller';
        $vendorId = $sellerIs === 'admin' ? 0 : (int) ($sellerId ?? 0);

        return DB::table('vendor_shipping_companies')
            ->where('vendor_id', $vendorId)
            ->whereRaw('LOWER(name) = ?', ['noest'])
            ->where('status', 1)
            ->first();
    }
}