<?php

namespace App\Http\Controllers\RestAPI\v1;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartShipping;
use App\Models\ShippingMethod;
use App\Models\ShippingType;
use App\Utils\CartManager;
use App\Utils\Helpers;
use App\Utils\OrderManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

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

    public function choose_for_order(Request $request)
{
    $validator = Validator::make($request->all(), [
        'cart_group_id' => 'required',
        'id' => 'required',
        'shipping_cost' => 'nullable|numeric',
    ], [
        'id.required' => translate('shipping_id_is_required')
    ]);

    if ($validator->errors()->count() > 0) {
        return response()->json(['errors' => Helpers::validationErrorProcessor($validator)]);
    }

    if ($request['cart_group_id'] == 'all_cart_group') {
        foreach (CartManager::get_cart_group_ids(request: $request) as $group_id) {
            $request['cart_group_id'] = $group_id;
            self::insert_into_cart_shipping($request);
        }
    } else {
        self::insert_into_cart_shipping($request);
    }

    return response()->json(translate('successfully_added'));
}

public static function insert_into_cart_shipping($request)
{
    $shipping = CartShipping::where(['cart_group_id' => $request['cart_group_id']])->first();

    if (!$shipping) {
        $shipping = new CartShipping();
    }

    $shippingId = (int) $request['id'];
    $shipping['cart_group_id'] = $request['cart_group_id'];
    $shipping['shipping_method_id'] = $shippingId;

    // إذا التطبيق أرسل السعر، نعتمده
    if (isset($request['shipping_cost']) && $request['shipping_cost'] !== null && $request['shipping_cost'] !== '') {
        $shipping['shipping_cost'] = (double) $request['shipping_cost'];
    } else {
        // fallback للشحن القديم
        $method = ShippingMethod::find($shippingId);
        $shipping['shipping_cost'] = (double) ($method->cost ?? 0);
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

        if($request->seller_is == 'admin')
        {
            $admin_shipping = ShippingType::where('seller_id',0)->first();
            $shipping_type = isset($admin_shipping)==true?$admin_shipping->shipping_type:'order_wise';

        }
        else{
            $seller_shipping = ShippingType::where('seller_id',$request->seller_id)->first();
            $shipping_type = isset($seller_shipping)==true? $seller_shipping->shipping_type:'order_wise';

        }
        return response()->json(['shipping_type'=>$shipping_type], 200);
    }
}
