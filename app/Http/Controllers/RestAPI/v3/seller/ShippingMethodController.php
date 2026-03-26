<?php

namespace App\Http\Controllers\RestAPI\v3\seller;

use App\Http\Controllers\Controller;
use App\Models\ShippingMethod;
use App\Utils\BackEndHelper;
use App\Utils\Helpers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class ShippingMethodController extends Controller
{
    public function store(Request $request)
    {
        $seller = $request->seller;
        $validator = Validator::make($request->all(), [
            'title' => 'required|max:200',
            'duration' => 'required',
            'cost' => 'numeric'
        ]);

        if ($validator->errors()->count() > 0) {
            return response()->json(['errors' => Helpers::validationErrorProcessor($validator)]);
        }

        DB::table('shipping_methods')->insert([
            'creator_id' => $seller['id'],
            'creator_type' => 'seller',
            'title' => $request['title'],
            'duration' => $request['duration'],
            'cost' => BackEndHelper::currency_to_usd($request['cost']),
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        return response()->json(['message' => translate('successfully_added')], 200);
    }

    public function list(Request $request)
    {
        $seller = $request->seller;
        $shipping_method = ShippingMethod::where(['creator_type' => 'seller', 'creator_id' => $seller['id']])->get();

        return response()->json($shipping_method, 200);
    }

    public function status_update(Request $request)
    {
        $seller = $request->seller;
        $validator = Validator::make($request->all(), [
            'id' => 'required',
            'status' => 'required|in:1,0',
        ]);

        if ($validator->errors()->count() > 0) {
            return response()->json(['errors' => Helpers::validationErrorProcessor($validator)]);
        }

        ShippingMethod::where(['id' => $request['id'], 'creator_id' => $seller['id']])->update([
            'status' => $request['status']
        ]);

        return response()->json(['message' => translate('successfully_status_updated')], 200);
    }

    public function edit(Request $request, $id)
    {
        $seller = $request->seller;
        $method = ShippingMethod::where(['id' => $id, 'creator_id' => $seller['id']])->first();
        if (isset($method)) {
            return response()->json($method, 200);
        }

        return response()->json(['message' => translate('data_not_found')], 200);
    }

    public function update(Request $request, $id)
    {
        $seller = $request->seller;
        $validator = Validator::make($request->all(), [
            'title' => 'required|max:200',
            'duration' => 'required',
            'cost' => 'numeric'
        ]);

        if ($validator->errors()->count() > 0) {
            return response()->json(['errors' => Helpers::validationErrorProcessor($validator)]);
        }

        DB::table('shipping_methods')->where(['id' => $id, 'creator_id' => $seller['id']])->update([
            'title' => $request['title'],
            'duration' => $request['duration'],
            'cost' => BackEndHelper::currency_to_usd($request['cost']),
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        return response()->json(['message' => translate('successfully_updated')], 200);
    }

    public function delete(Request $request)
    {
        $seller = $request->seller;
        ShippingMethod::where(['id' => $request->id, 'creator_id' => $seller['id']])->delete();

        return response()->json(['message' => translate('successfully_deleted')], 200);
    }
    
    public function get_noest_settings(Request $request)
{
    $data = Helpers::get_seller_by_token($request);

    if ($data['success'] == 1) {
        $seller = $data['data'];
    } else {
        return response()->json([
            'auth-001' => translate('Your existing session token does not authorize you any more')
        ], 401);
    }

    $noest = DB::table('vendor_shipping_companies')
        ->where('vendor_id', $seller['id'])
        ->whereRaw('LOWER(name) = ?', ['noest'])
        ->first();

    $connectedSince = null;
    if ($noest && Schema::hasColumn('vendor_shipping_companies', 'connected_since')) {
        $connectedSince = $noest->connected_since;
    }

    $isConnected = 0;
    if (
        $noest &&
        !empty($noest->noest_guid) &&
        !empty($noest->api_token) &&
        (int)($noest->status ?? 0) === 1
    ) {
        $isConnected = 1;
    }

    return response()->json([
        'name' => 'noest',
        'display_name' => 'NOEST',
        'vendor_id' => (int)$seller['id'],
        'noest_guid' => (string)($noest->noest_guid ?? ''),
        'api_token' => (string)($noest->api_token ?? ''),
        'status' => (int)($noest->status ?? 0),
        'connected_since' => $connectedSince,
        'is_connected' => $isConnected,
        'delivery_methods' => [
            [
                'id' => 'home_delivery',
                'title' => 'home_delivery',
                'name' => 'home_delivery',
                'status' => 1,
            ],
            [
                'id' => 'desk_delivery',
                'title' => 'desk_delivery',
                'name' => 'desk_delivery',
                'status' => 1,
            ],
        ],
    ], 200);
}

public function save_noest_settings(Request $request)
{
    $data = Helpers::get_seller_by_token($request);

    if ($data['success'] == 1) {
        $seller = $data['data'];
    } else {
        return response()->json([
            'auth-001' => translate('Your existing session token does not authorize you any more')
        ], 401);
    }

    $validator = Validator::make($request->all(), [
        'noest_guid' => 'required|string|max:255',
        'api_token' => 'required|string|max:500',
        'status' => 'nullable|in:0,1',
    ]);

    if ($validator->errors()->count() > 0) {
        return response()->json(['errors' => Helpers::validationErrorProcessor($validator)]);
    }

    $existingNoest = DB::table('vendor_shipping_companies')
        ->where('vendor_id', $seller['id'])
        ->whereRaw('LOWER(name) = ?', ['noest'])
        ->first();

    $status = $request->has('status') ? (int)$request['status'] : 0;

    $payload = [
        'vendor_id' => $seller['id'],
        'name' => 'noest',
        'noest_guid' => $request['noest_guid'],
        'api_token' => $request['api_token'],
        'status' => $status,
    ];

    if (Schema::hasColumn('vendor_shipping_companies', 'updated_at')) {
        $payload['updated_at'] = now();
    }

    if (!$existingNoest && Schema::hasColumn('vendor_shipping_companies', 'created_at')) {
        $payload['created_at'] = now();
    }

    if (
        Schema::hasColumn('vendor_shipping_companies', 'connected_since') &&
        empty($existingNoest->connected_since) &&
        $status === 1
    ) {
        $payload['connected_since'] = now();
    }

    if ($existingNoest) {
        DB::table('vendor_shipping_companies')
            ->where('id', $existingNoest->id)
            ->update($payload);
    } else {
        DB::table('vendor_shipping_companies')->insert($payload);
    }

    return response()->json([
        'message' => translate('successfully_updated')
    ], 200);
}

public function test_noest_connection(Request $request)
{
    $data = Helpers::get_seller_by_token($request);

    if ($data['success'] == 1) {
        $seller = $data['data'];
    } else {
        return response()->json([
            'auth-001' => translate('Your existing session token does not authorize you any more')
        ], 401);
    }

    $existingNoest = DB::table('vendor_shipping_companies')
        ->where('vendor_id', $seller['id'])
        ->whereRaw('LOWER(name) = ?', ['noest'])
        ->first();

    $noestGuid = $request['noest_guid'] ?? ($existingNoest->noest_guid ?? null);
    $apiToken = $request['api_token'] ?? ($existingNoest->api_token ?? null);

    if (empty($noestGuid) || empty($apiToken)) {
        return response()->json([
            'success' => 0,
            'message' => 'NOEST credentials are required',
        ], 422);
    }

    try {
        $response = Http::timeout(15)
            ->connectTimeout(5)
            ->acceptJson()
            ->withToken($apiToken)
            ->get('https://app.noest-dz.com/api/public/desks');

        $responseData = $response->json();
        $success = $response->successful() && is_array($responseData);

        if ($success && $existingNoest && Schema::hasColumn('vendor_shipping_companies', 'connected_since')) {
            $updateData = [];

            if (empty($existingNoest->connected_since)) {
                $updateData['connected_since'] = now();
            }

            if (Schema::hasColumn('vendor_shipping_companies', 'updated_at')) {
                $updateData['updated_at'] = now();
            }

            if (!empty($updateData)) {
                DB::table('vendor_shipping_companies')
                    ->where('id', $existingNoest->id)
                    ->update($updateData);
            }
        }

        return response()->json([
            'success' => $success ? 1 : 0,
            'message' => $success ? 'NOEST connection successful' : 'Failed to connect with NOEST',
            'desks_count' => $success ? count($responseData) : 0,
        ], $success ? 200 : 422);

    } catch (\Throwable $exception) {
        return response()->json([
            'success' => 0,
            'message' => $exception->getMessage(),
        ], 422);
    }
}
}
