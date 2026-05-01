<?php

namespace Tests\Feature;

use App\Contracts\Shipping\ShippingCarrierDriverInterface;
use App\DTO\Shipping\ShipmentCreateResult;
use App\DTO\Shipping\ShipmentTrackingResult;
use App\Http\Controllers\RestAPI\v3\seller\ShippingCarrierController;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Services\Shipping\ShippingCarrierManager;
use App\Services\ShippingOrderService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MultiCarrierShippingTest extends TestCase
{
    private ShippingCarrierManager $shippingCarrierManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpDatabase();
        $this->shippingCarrierManager = app(ShippingCarrierManager::class);

        config([
            'shipping_carriers.carriers.carrier_a' => [
                'key' => 'carrier_a',
                'display_name' => 'Carrier A',
                'website' => 'https://carrier-a.test',
                'driver' => FakeSupportedCarrierDriver::class,
                'status' => true,
                'supported' => true,
                'integration_status' => 'supported',
                'supports_home_delivery' => true,
                'supports_desk_delivery' => false,
                'supports_rate_lookup' => true,
                'supports_tracking' => true,
                'supports_label' => false,
                'supports_create_shipment' => true,
                'credential_fields' => [
                    ['key' => 'api_key', 'label' => 'api_token', 'type' => 'password'],
                ],
                'required_fields' => ['api_key'],
            ],
            'shipping_carriers.carriers.carrier_fail' => [
                'key' => 'carrier_fail',
                'display_name' => 'Carrier Fail',
                'website' => 'https://carrier-fail.test',
                'driver' => FakeFailingCarrierDriver::class,
                'status' => true,
                'supported' => true,
                'integration_status' => 'supported',
                'supports_home_delivery' => true,
                'supports_desk_delivery' => false,
                'supports_rate_lookup' => true,
                'supports_tracking' => false,
                'supports_label' => false,
                'supports_create_shipment' => true,
                'credential_fields' => [
                    ['key' => 'api_key', 'label' => 'api_token', 'type' => 'password'],
                ],
                'required_fields' => ['api_key'],
            ],
            'shipping_carriers.carriers.maystro.status' => true,
            'shipping_carriers.carriers.maystro.supported' => true,
            'shipping_carriers.carriers.maystro.integration_status' => 'supported',
        ]);
    }

    protected function tearDown(): void
    {
        foreach (['shipping_events', 'order_details', 'order_shipping_details', 'orders', 'vendor_shipping_companies', 'business_settings', 'wilayas'] as $table) {
            Schema::dropIfExists($table);
        }

        parent::tearDown();
    }

    public function test_noest_legacy_settings_still_work(): void
    {
        $record = $this->shippingCarrierManager->saveVendorCarrierSettings(28, 'noest', [
            'noest_guid' => 'guid-123',
            'api_token' => 'token-123',
            'status' => 1,
        ]);

        $this->assertSame('noest', $record->name);
        $this->assertSame('guid-123', $record->noest_guid);
        $this->assertSame('token-123', $record->api_token);
        $this->assertSame('noest', $record->carrier_key);
        $this->assertSame('https://app.noest-dz.com', $record->website);
    }

    public function test_seller_can_save_yalidine_credentials_without_website_error(): void
    {
        $record = $this->shippingCarrierManager->saveVendorCarrierSettings(38, 'yalidine', [
            'api_id' => 'yal-id',
            'api_token' => 'yal-token',
            'from_wilaya_id' => '16',
            'status' => 1,
        ]);

        $this->assertSame('yalidine', $record->name);
        $this->assertSame('Yalidine', $record->display_name);
        $this->assertSame('https://yalidine.app', $record->website);
    }

    public function test_seller_can_save_maystro_credentials_without_website_error(): void
    {
        $record = $this->shippingCarrierManager->saveVendorCarrierSettings(39, 'maystro', [
            'store_token' => 'demo-token',
            'store_id' => 'store-39',
            'status' => 1,
        ]);

        $this->assertSame('maystro', $record->name);
        $this->assertSame('Maystro Delivery', $record->display_name);
        $this->assertSame('https://orders-management.maystro-delivery.com', $record->website);
    }

    public function test_website_fallback_is_applied_if_config_website_is_missing(): void
    {
        config([
            'shipping_carriers.carriers.carrier_a.website' => null,
            'shipping_carriers.carriers.carrier_a.base_url' => 'https://carrier-a-base.test',
        ]);

        $record = $this->shippingCarrierManager->saveVendorCarrierSettings(40, 'carrier_a', [
            'api_key' => 'valid-key',
            'status' => 1,
        ]);

        $this->assertSame('carrier_a', $record->name);
        $this->assertSame('Carrier A', $record->display_name);
        $this->assertSame('https://carrier-a-base.test', $record->website);
    }

    public function test_maystro_still_works_after_other_carrier_changes(): void
    {
        Http::fake([
            'https://orders-management.maystro-delivery.com/api/base/delivery-options/*' => Http::response([
                [
                    'type' => 'home',
                    'name' => 'Home',
                    'price' => 400,
                ],
            ], 200),
        ]);

        $this->shippingCarrierManager->saveVendorCarrierSettings(24, 'maystro', [
            'store_token' => 'demo-token',
            'store_id' => 'store-24',
            'status' => 1,
        ]);
        $this->shippingCarrierManager->toggleVendorCarrier(24, 'maystro', true);

        $availableCarriers = $this->shippingCarrierManager->getAvailableCarriersForSeller(24, [
            'seller_id' => 24,
            'seller_is' => 'seller',
            'wilaya_id' => 16,
            'wilaya_code' => '16',
            'wilaya_name' => 'Alger',
            'commune_id' => 575,
        ]);

        $this->assertCount(1, $availableCarriers);
        $this->assertSame('maystro', $availableCarriers[0]['carrier_key']);
        $this->assertSame('home_delivery', $availableCarriers[0]['delivery_methods'][0]['type']);
    }

    public function test_yalidine_credentials_are_masked_and_masked_value_does_not_overwrite_real_secret(): void
    {
        $controller = app(ShippingCarrierController::class);

        $storeRequest = Request::create('/api/v3/seller/shipping-carriers/yalidine/settings', 'POST', [
            'api_id' => 'yal-id',
            'api_token' => 'yal-secret-token',
            'from_wilaya_id' => '16',
            'status' => 1,
        ]);
        $storeRequest->seller = ['id' => 30];

        $this->assertSame(200, $controller->store($storeRequest, 'yalidine')->status());

        $showRequest = Request::create('/api/v3/seller/shipping-carriers/yalidine/settings', 'GET');
        $showRequest->seller = ['id' => 30];
        $payload = $controller->show($showRequest, 'yalidine')->getData(true);

        $tokenField = collect($payload['credential_fields'])->firstWhere('key', 'api_token');
        $this->assertNull($tokenField['value']);
        $this->assertNotEmpty($tokenField['masked_value']);
        $this->assertStringNotContainsString('yal-secret-token', json_encode($payload));

        $maskedRequest = Request::create('/api/v3/seller/shipping-carriers/yalidine/settings', 'POST', [
            'api_id' => 'yal-id',
            'api_token' => $tokenField['masked_value'],
            'from_wilaya_id' => '16',
            'status' => 1,
        ]);
        $maskedRequest->seller = ['id' => 30];

        $this->assertSame(200, $controller->store($maskedRequest, 'yalidine')->status());

        $carrierRecord = $this->shippingCarrierManager->getVendorCarrier(30, 'yalidine');
        $credentials = $this->shippingCarrierManager->getVendorCarrierCredentials($carrierRecord);

        $this->assertSame('yal-secret-token', $credentials['api_token']);
    }

    public function test_yalidine_test_connection_rates_and_create_shipment_work(): void
    {
        Http::fake([
            'https://api.yalidine.app/v1/wilayas*' => Http::response([
                'data' => [
                    ['id' => 16, 'name' => 'Alger'],
                ],
            ], 200),
            'https://api.yalidine.app/v1/fees*' => Http::response([
                'from_wilaya_name' => 'Alger',
                'to_wilaya_name' => 'Alger',
                'per_commune' => [
                    '575' => [
                        'commune_id' => 575,
                        'commune_name' => 'Hydra',
                        'express_home' => 500,
                        'express_desk' => 350,
                    ],
                ],
            ], 200),
            'https://api.yalidine.app/v1/parcels*' => Http::response([
                'GROUP-31' => [
                    'success' => true,
                    'message' => 'Created',
                    'tracking' => 'YAL-31',
                    'delivery_fee' => 500,
                    'label' => 'https://label.test/yal-31.pdf',
                ],
            ], 200),
        ]);

        $this->assertTrue($this->exerciseYalidineLikeCarrierFlow('yalidine', 31, 'YAL-31'));
    }

    public function test_guepex_test_connection_rates_and_create_shipment_work(): void
    {
        Http::fake([
            'https://api.guepex.app/v1/wilayas*' => Http::response([
                'data' => [
                    ['id' => 16, 'name' => 'Alger'],
                ],
            ], 200),
            'https://api.guepex.app/v1/fees*' => Http::response([
                'from_wilaya_name' => 'Alger',
                'to_wilaya_name' => 'Alger',
                'per_commune' => [
                    '575' => [
                        'commune_id' => 575,
                        'commune_name' => 'Hydra',
                        'express_home' => 520,
                        'express_desk' => 360,
                    ],
                ],
            ], 200),
            'https://api.guepex.app/v1/parcels*' => Http::response([
                'GROUP-32' => [
                    'success' => true,
                    'message' => 'Created',
                    'tracking' => 'GXP-32',
                    'delivery_fee' => 520,
                    'label' => 'https://label.test/gxp-32.pdf',
                ],
            ], 200),
        ]);

        $this->assertTrue($this->exerciseYalidineLikeCarrierFlow('guepex', 32, 'GXP-32'));
    }

    public function test_procolis_test_connection_rates_and_create_shipment_work(): void
    {
        Http::fake([
            'https://procolis.com/api_v1/token' => Http::response([
                'Statut' => 'Accès activé',
            ], 200),
            'https://procolis.com/api_v1/tarification' => Http::response([
                [
                    'IDWilaya' => 16,
                    'TarifDomicile' => 650,
                    'TarifStopDesk' => 450,
                ],
            ], 200),
            'https://procolis.com/api_v1/add_colis' => Http::response([
                'Colis' => [
                    [
                        'MessageRetour' => 'Good',
                        'Tracking' => 'PRO-33',
                    ],
                ],
            ], 200),
        ]);

        $this->assertTrue($this->exerciseProcolisLikeCarrierFlow('procolis', 33, 'PRO-33'));
    }

    public function test_zr_express_test_connection_rates_and_create_shipment_work(): void
    {
        Http::fake([
            'https://procolis.com/api_v1/token' => Http::response([
                'Statut' => 'Accès activé',
            ], 200),
            'https://procolis.com/api_v1/tarification' => Http::response([
                [
                    'IDWilaya' => 16,
                    'TarifDomicile' => 700,
                    'TarifStopDesk' => 500,
                ],
            ], 200),
            'https://procolis.com/api_v1/add_colis' => Http::response([
                'Colis' => [
                    [
                        'MessageRetour' => 'Good',
                        'Tracking' => 'ZRE-34',
                    ],
                ],
            ], 200),
        ]);

        $this->assertTrue($this->exerciseProcolisLikeCarrierFlow('zr_express', 34, 'ZRE-34'));
    }

    public function test_unavailable_method_returns_supported_false_without_crash(): void
    {
        $yalidineResult = app(\App\Services\Shipping\Drivers\YalidineDriver::class)
            ->getAvailableCommunes(['api_id' => 'id', 'api_token' => 'token'], 16);
        $procolisLabelResult = app(\App\Services\Shipping\Drivers\ProcolisDriver::class)
            ->getLabel('TRACK-1', ['token' => 'token', 'key' => 'secret']);

        $this->assertFalse($yalidineResult['supported']);
        $this->assertFalse($procolisLabelResult['supported']);
        $this->assertSame('carrier_feature_not_supported_yet', $yalidineResult['message']);
    }

    public function test_available_carriers_returns_only_enabled_carriers_with_rates(): void
    {
        Http::fake([
            'https://api.yalidine.app/v1/fees*' => Http::response([
                'from_wilaya_name' => 'Alger',
                'to_wilaya_name' => 'Alger',
                'per_commune' => [
                    '575' => [
                        'commune_id' => 575,
                        'commune_name' => 'Hydra',
                        'express_home' => 500,
                    ],
                ],
            ], 200),
        ]);

        $this->shippingCarrierManager->saveVendorCarrierSettings(35, 'yalidine', [
            'api_id' => 'yal-id',
            'api_token' => 'yal-token',
            'from_wilaya_id' => '16',
            'status' => 1,
        ]);
        $this->shippingCarrierManager->toggleVendorCarrier(35, 'yalidine', true);

        $this->shippingCarrierManager->saveVendorCarrierSettings(35, 'guepex', [
            'api_id' => 'gxp-id',
            'api_token' => 'gxp-token',
            'from_wilaya_id' => '16',
            'status' => 0,
        ]);

        $availableCarriers = $this->shippingCarrierManager->getAvailableCarriersForSeller(35, [
            'seller_id' => 35,
            'seller_is' => 'seller',
            'wilaya_id' => 16,
            'wilaya_code' => '16',
            'wilaya_name' => 'Alger',
            'commune_id' => 575,
        ]);

        $this->assertCount(1, $availableCarriers);
        $this->assertSame('yalidine', $availableCarriers[0]['carrier_key']);
    }

    public function test_create_shipment_failure_does_not_crash_order_creation(): void
    {
        $this->shippingCarrierManager->saveVendorCarrierSettings(36, 'carrier_fail', [
            'api_key' => 'valid-key',
            'status' => 1,
        ]);
        $this->shippingCarrierManager->toggleVendorCarrier(36, 'carrier_fail', true);

        $order = $this->makeOrder(36, 'carrier_fail', [
            'carrier_name' => 'Carrier Fail',
        ], 'GROUP-36', 1900);

        ShippingOrderService::createShipment($order);

        $this->assertDatabaseHas('orders', ['id' => $order->id]);
        $this->assertDatabaseHas('order_shipping_details', [
            'order_id' => $order->id,
            'carrier_key' => 'carrier_fail',
            'shipping_status' => 'failed',
        ]);
    }

    public function test_tracking_sync_maps_statuses_safely(): void
    {
        $this->shippingCarrierManager->saveVendorCarrierSettings(37, 'carrier_a', [
            'api_key' => 'valid-key',
            'status' => 1,
        ]);
        $this->shippingCarrierManager->toggleVendorCarrier(37, 'carrier_a', true);

        $order = Order::query()->create([
            'seller_id' => 37,
            'seller_is' => 'seller',
            'order_group_id' => 'ORD-TRACK-1',
            'order_amount' => 2000,
            'shipping_type' => 'order_wise',
            'order_status' => 'pending',
            'payment_status' => 'unpaid',
            'payment_method' => 'cash_on_delivery',
        ]);

        DB::table('order_shipping_details')->insert([
            'order_id' => $order->id,
            'seller_id' => 37,
            'carrier_key' => 'carrier_a',
            'carrier_name' => 'Carrier A',
            'tracking_number' => 'TRACK-100',
            'shipping_status' => 'created',
            'status' => 'created',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $updatedCount = $this->shippingCarrierManager->syncOpenShipments();

        $this->assertSame(1, $updatedCount);
        $this->assertSame('delivered', $order->fresh()->order_status);
        $this->assertDatabaseHas('shipping_events', [
            'order_id' => $order->id,
            'carrier_key' => 'carrier_a',
            'shipping_status' => 'delivered',
        ]);
    }

    public function test_arabic_carrier_labels_use_translation_keys_not_hardcoded_mojibake(): void
    {
        $translations = include resource_path('lang/ae/new-messages.php');

        $this->assertSame('api_id', config('shipping_carriers.carriers.yalidine.credential_fields.0.label'));
        $this->assertSame('api_token', config('shipping_carriers.carriers.yalidine.credential_fields.1.label'));
        $this->assertSame('from_wilaya_id', config('shipping_carriers.carriers.yalidine.credential_fields.2.label'));
        $this->assertArrayHasKey('from_wilaya_id', $translations);
        $this->assertStringNotContainsString('Ã', $translations['from_wilaya_id']);
    }

    private function exerciseYalidineLikeCarrierFlow(string $carrierKey, int $vendorId, string $expectedTracking): bool
    {
        $this->shippingCarrierManager->saveVendorCarrierSettings($vendorId, $carrierKey, [
            'api_id' => $carrierKey . '-id',
            'api_token' => $carrierKey . '-token',
            'from_wilaya_id' => '16',
            'status' => 1,
        ]);
        $this->shippingCarrierManager->toggleVendorCarrier($vendorId, $carrierKey, true);

        $connectionResult = $this->shippingCarrierManager->testVendorCarrierConnection($vendorId, $carrierKey, [
            'api_id' => $carrierKey . '-id',
            'api_token' => $carrierKey . '-token',
            'from_wilaya_id' => '16',
            'status' => 1,
        ]);

        $this->assertTrue($connectionResult['success']);

        $availableCarriers = $this->shippingCarrierManager->getAvailableCarriersForSeller($vendorId, [
            'seller_id' => $vendorId,
            'seller_is' => 'seller',
            'wilaya_id' => 16,
            'wilaya_code' => '16',
            'wilaya_name' => 'Alger',
            'commune_id' => 575,
        ]);

        $this->assertCount(1, $availableCarriers);
        $this->assertSame($carrierKey, $availableCarriers[0]['carrier_key']);
        $this->assertCount(2, $availableCarriers[0]['delivery_methods']);

        $order = $this->makeOrder($vendorId, $carrierKey, [
            'carrier_name' => $carrierKey === 'yalidine' ? 'Yalidine' : 'Guepex',
        ], 'GROUP-' . $vendorId, 2800);

        $result = $this->shippingCarrierManager->createShipmentForOrder($order);

        $this->assertTrue($result->success);
        $this->assertSame($expectedTracking, $result->trackingNumber);

        return true;
    }

    private function exerciseProcolisLikeCarrierFlow(string $carrierKey, int $vendorId, string $expectedTracking): bool
    {
        $this->shippingCarrierManager->saveVendorCarrierSettings($vendorId, $carrierKey, [
            'token' => $carrierKey . '-token',
            'key' => $carrierKey . '-secret',
            'status' => 1,
        ]);
        $this->shippingCarrierManager->toggleVendorCarrier($vendorId, $carrierKey, true);

        $connectionResult = $this->shippingCarrierManager->testVendorCarrierConnection($vendorId, $carrierKey, [
            'token' => $carrierKey . '-token',
            'key' => $carrierKey . '-secret',
            'status' => 1,
        ]);

        $this->assertTrue($connectionResult['success']);

        $availableCarriers = $this->shippingCarrierManager->getAvailableCarriersForSeller($vendorId, [
            'seller_id' => $vendorId,
            'seller_is' => 'seller',
            'wilaya_id' => 16,
            'wilaya_code' => '16',
            'wilaya_name' => 'Alger',
            'commune_id' => 575,
        ]);

        $this->assertCount(1, $availableCarriers);
        $this->assertSame($carrierKey, $availableCarriers[0]['carrier_key']);
        $this->assertCount(2, $availableCarriers[0]['delivery_methods']);

        $order = $this->makeOrder($vendorId, $carrierKey, [
            'carrier_name' => $carrierKey === 'procolis' ? 'Procolis' : 'ZR Express',
            'delivery_type' => 'desk_delivery',
        ], 'GROUP-' . $vendorId, 3100);

        $result = $this->shippingCarrierManager->createShipmentForOrder($order);

        $this->assertTrue($result->success);
        $this->assertSame($expectedTracking, $result->trackingNumber);

        return true;
    }

    private function makeOrder(
        int $sellerId,
        string $carrierKey,
        array $shippingAddressOverrides = [],
        string $orderGroupId = 'GROUP-ORDER',
        float $orderAmount = 2800
    ): Order {
        $shippingAddress = array_merge([
            'carrier_key' => $carrierKey,
            'carrier_name' => strtoupper($carrierKey),
            'delivery_type' => 'home_delivery',
            'contact_person_name' => 'Customer Test',
            'phone' => '0555123456',
            'address' => 'Alger address',
            'wilaya_id' => 16,
            'wilaya_name' => 'Alger',
            'commune_id' => 575,
            'commune_name' => 'Hydra',
            'city' => 'Hydra',
            'shipping_cost' => 0,
        ], $shippingAddressOverrides);

        $order = Order::query()->create([
            'seller_id' => $sellerId,
            'seller_is' => 'seller',
            'order_group_id' => $orderGroupId,
            'order_amount' => $orderAmount,
            'shipping_cost' => 0,
            'shipping_type' => 'order_wise',
            'shipping_address_data' => $shippingAddress,
            'order_status' => 'pending',
            'payment_status' => 'unpaid',
            'payment_method' => 'cash_on_delivery',
        ]);

        $order->setRelation('details', collect([
            new OrderDetail([
                'qty' => 1,
                'product_details' => json_encode(['name' => 'Product Alpha']),
            ]),
        ]));

        return $order;
    }

    private function setUpDatabase(): void
    {
        foreach (['shipping_events', 'order_details', 'order_shipping_details', 'orders', 'vendor_shipping_companies', 'business_settings', 'wilayas'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('business_settings', function (Blueprint $table) {
            $table->id();
            $table->string('type')->nullable();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        DB::table('business_settings')->insert([
            'type' => 'language',
            'value' => json_encode([
                ['code' => 'en', 'default' => true, 'direction' => 'ltr'],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::create('vendor_shipping_companies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vendor_id');
            $table->string('name')->nullable();
            $table->string('website');
            $table->string('noest_guid')->nullable();
            $table->string('api_token')->nullable();
            $table->string('carrier_key')->nullable();
            $table->string('display_name')->nullable();
            $table->longText('credentials')->nullable();
            $table->tinyInteger('status')->default(0);
            $table->boolean('supports_home_delivery')->default(false);
            $table->boolean('supports_desk_delivery')->default(false);
            $table->timestamp('connected_since')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->text('last_error')->nullable();
            $table->boolean('is_connected')->default(false);
            $table->timestamps();
        });

        Schema::create('wilayas', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        DB::table('wilayas')->insert([
            'id' => 16,
            'code' => '16',
            'name' => 'Alger',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_id')->nullable();
            $table->string('seller_is')->nullable();
            $table->string('order_group_id')->nullable();
            $table->decimal('order_amount', 12, 2)->default(0);
            $table->decimal('shipping_cost', 12, 2)->default(0);
            $table->string('shipping_type')->nullable();
            $table->json('shipping_address_data')->nullable();
            $table->string('delivery_type')->nullable();
            $table->string('delivery_service_name')->nullable();
            $table->string('third_party_delivery_tracking_id')->nullable();
            $table->string('order_status')->nullable();
            $table->string('payment_status')->nullable();
            $table->string('payment_method')->nullable();
            $table->text('order_note')->nullable();
            $table->timestamps();
        });

        Schema::create('order_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('seller_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->text('product_details')->nullable();
            $table->integer('qty')->default(1);
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('order_shipping_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->unique();
            $table->unsignedBigInteger('seller_id')->nullable();
            $table->string('carrier_key')->nullable();
            $table->string('carrier_name')->nullable();
            $table->string('delivery_service_name')->nullable();
            $table->string('service_name')->nullable();
            $table->string('delivery_type')->nullable();
            $table->string('tracking_number')->nullable();
            $table->string('tracking_id')->nullable();
            $table->string('tracking')->nullable();
            $table->string('remote_order_id')->nullable();
            $table->string('remote_display_id')->nullable();
            $table->decimal('delivery_price', 12, 2)->nullable();
            $table->string('shipping_status')->nullable();
            $table->string('status')->nullable();
            $table->longText('shipment_payload')->nullable();
            $table->longText('request_payload')->nullable();
            $table->longText('shipment_response')->nullable();
            $table->longText('response_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->string('desk_code')->nullable();
            $table->string('desk_name')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('shipping_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('order_shipping_detail_id')->nullable();
            $table->string('carrier_key')->nullable();
            $table->string('tracking_number')->nullable();
            $table->string('shipping_status')->nullable();
            $table->string('event_label')->nullable();
            $table->text('event_description')->nullable();
            $table->longText('event_payload')->nullable();
            $table->timestamp('event_at')->nullable();
            $table->timestamps();
        });
    }
}

class FakeSupportedCarrierDriver implements ShippingCarrierDriverInterface
{
    public function validateCredentials(array $credentials): array
    {
        return [
            'supported' => true,
            'success' => ($credentials['api_key'] ?? null) === 'valid-key',
            'message' => 'ok',
            'data' => [],
        ];
    }

    public function getAvailableWilayas(array $credentials): array
    {
        return ['supported' => true, 'success' => true, 'message' => null, 'data' => []];
    }

    public function getAvailableCommunes(array $credentials, mixed $wilaya): array
    {
        return ['supported' => true, 'success' => true, 'message' => null, 'data' => []];
    }

    public function getDesks(array $credentials, mixed $wilaya = null): array
    {
        return ['supported' => true, 'success' => true, 'message' => null, 'data' => []];
    }

    public function getRates(array $credentials, array $payload): array
    {
        return [
            'supported' => true,
            'success' => true,
            'message' => null,
            'data' => [
                [
                    'type' => 'home_delivery',
                    'price' => 500,
                    'currency' => 'DZD',
                    'estimated_delivery' => '24-72h',
                ],
            ],
        ];
    }

    public function createShipment(Order $order, array $credentials, array $options = []): ShipmentCreateResult
    {
        return new ShipmentCreateResult(
            supported: true,
            success: true,
            message: 'ok',
            carrierKey: $options['carrier_key'] ?? 'carrier_a',
            carrierName: $options['carrier_name'] ?? 'Carrier A',
            deliveryType: $options['delivery_type'] ?? 'home_delivery',
            trackingNumber: 'TRACK-OK-1',
            shippingStatus: 'created',
            payload: ['reference' => $order->order_group_id],
            response: ['success' => true],
            errorMessage: null,
        );
    }

    public function trackShipment(string $trackingNumber, array $credentials): ShipmentTrackingResult
    {
        return new ShipmentTrackingResult(
            supported: true,
            success: true,
            message: 'ok',
            carrierKey: 'carrier_a',
            carrierName: 'Carrier A',
            trackingNumber: $trackingNumber,
            shippingStatus: 'delivered',
            events: [
                [
                    'shipping_status' => 'delivered',
                    'label' => 'Delivered',
                    'description' => 'Package delivered',
                    'event_at' => now(),
                ],
            ],
            response: ['status' => 'delivered'],
            errorMessage: null,
        );
    }

    public function cancelShipment(string $trackingNumber, array $credentials): array
    {
        return ['supported' => true, 'success' => true, 'message' => 'ok', 'data' => []];
    }

    public function getLabel(string $trackingNumber, array $credentials): mixed
    {
        return ['supported' => true, 'success' => false, 'message' => 'not_implemented'];
    }
}

class FakeFailingCarrierDriver extends FakeSupportedCarrierDriver
{
    public function createShipment(Order $order, array $credentials, array $options = []): ShipmentCreateResult
    {
        return new ShipmentCreateResult(
            supported: true,
            success: false,
            message: 'failed',
            carrierKey: $options['carrier_key'] ?? 'carrier_fail',
            carrierName: $options['carrier_name'] ?? 'Carrier Fail',
            deliveryType: $options['delivery_type'] ?? 'home_delivery',
            trackingNumber: null,
            shippingStatus: 'failed',
            payload: ['reference' => $order->order_group_id],
            response: ['success' => false],
            errorMessage: 'carrier_api_failed',
        );
    }
}
