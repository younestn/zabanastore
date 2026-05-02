<?php

namespace Tests\Feature;

use App\Http\Controllers\VendorAdRequestController;
use App\Models\Admin;
use App\Models\AdPricingPlan;
use App\Models\AdRequest;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Seller;
use App\Services\AdRequestService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdRequestFeatureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!defined('DOMAIN_POINTED_DIRECTORY')) {
            define('DOMAIN_POINTED_DIRECTORY', '');
        }

        $this->setUpDatabase();

        config([
            'filesystems.disks.default' => 'public',
            'ad_requests.payment_settings.ad_default_price' => 2500,
            'ad_requests.payment_settings.ad_currency' => 'DZD',
            'ad_requests.payment_settings.ad_receipt_required' => 1,
            'get_theme_routes' => [],
            'addon_admin_routes' => [],
            'currency_symbol_position' => 'left',
        ]);
    }

    protected function tearDown(): void
    {
        foreach ([
            'reviews',
            'translations',
            'storages',
            'notification_seens',
            'notifications',
            'chattings',
            'order_details',
            'orders',
            'refund_requests',
            'contacts',
            'support_tickets',
            'seller_commission_alert_logs',
            'guest_users',
            'ad_requests',
            'ad_pricing_plans',
            'currencies',
            'brands',
            'categories',
            'products',
            'shops',
            'sellers',
            'admins',
            'business_settings',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        parent::tearDown();
    }

    public function test_seller_can_open_vendor_new_request_controller_view(): void
    {
        $seller = $this->createSeller();
        $this->createPricingPlan();

        $this->actingAs($seller, 'seller');

        $response = app(VendorAdRequestController::class)->create();

        $this->assertSame('vendor-views.ad-request.create', $response->name());
    }

    public function test_admin_index_shows_details_button_or_show_link(): void
    {
        $this->withoutMiddleware();

        $seller = $this->createSeller();
        $product = $this->createProduct($seller->id);
        $admin = $this->createAdmin();
        $plan = $this->createPricingPlan();
        $adRequest = $this->createAdRequest($seller->id, $product->id, $plan->id);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.ad-requests.index'));

        $response->assertOk();
        $response->assertSee(route('admin.ad-requests.show', $adRequest->id), false);
        $response->assertSee('details', false);
    }

    public function test_admin_can_open_ad_request_details_page(): void
    {
        $this->withoutMiddleware();

        $seller = $this->createSeller();
        $product = $this->createProduct($seller->id);
        $admin = $this->createAdmin();
        $plan = $this->createPricingPlan();
        $adRequest = $this->createAdRequest($seller->id, $product->id, $plan->id, [
            'title' => 'Details Ad',
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.ad-requests.show', $adRequest->id));

        $response->assertOk();
        $response->assertSee('Details Ad');
        $response->assertSee((string) $adRequest->id);
    }

    public function test_admin_can_create_ad_pricing_plan(): void
    {
        $this->withoutMiddleware();

        $admin = $this->createAdmin();

        $response = $this->actingAs($admin, 'admin')->post(route('admin.ad-requests.pricing.store'), [
            'name' => 'Featured Weekly',
            'placement' => 'featured_products',
            'description' => 'Featured products placement for one week',
            'price' => 2500,
            'duration_days' => 7,
            'currency' => 'DZD',
            'sort_order' => 1,
            'status' => 1,
        ]);

        $response->assertRedirect(route('admin.ad-requests.pricing.index'));

        $this->assertDatabaseHas('ad_pricing_plans', [
            'name' => 'Featured Weekly',
            'placement' => 'featured_products',
            'duration_days' => 7,
        ]);
    }

    public function test_admin_can_update_ad_pricing_plan(): void
    {
        $this->withoutMiddleware();

        $admin = $this->createAdmin();
        $plan = $this->createPricingPlan([
            'name' => 'Old Plan',
            'price' => 1200,
        ]);

        $response = $this->actingAs($admin, 'admin')->post(route('admin.ad-requests.pricing.update', $plan->id), [
            'name' => 'Updated Plan',
            'placement' => 'home_banner_classic',
            'description' => 'Updated description',
            'price' => 1800,
            'duration_days' => 5,
            'currency' => 'DZD',
            'sort_order' => 2,
            'status' => 1,
        ]);

        $response->assertRedirect(route('admin.ad-requests.pricing.index'));

        $plan->refresh();

        $this->assertSame('Updated Plan', $plan->name);
        $this->assertSame('home_banner_classic', $plan->placement);
        $this->assertSame(1800.0, (float) $plan->price);
        $this->assertSame(5, (int) $plan->duration_days);
    }

    public function test_seller_can_see_active_pricing_plans_only(): void
    {
        $this->withoutMiddleware();

        $seller = $this->createSeller();
        $activePlan = $this->createPricingPlan(['name' => 'Active Plan', 'status' => true, 'placement' => 'featured_products']);
        $this->createPricingPlan(['name' => 'Inactive Plan', 'status' => false]);
        $this->createPricingPlan(['name' => 'Legacy Home Top Plan', 'placement' => 'home_top', 'status' => true]);
        $this->createPricingPlan(['name' => 'Classic Banner Plan', 'placement' => 'home_banner_classic', 'status' => true]);

        $response = $this->actingAs($seller, 'seller')->get(route('vendor.vendor1.test'));

        $response->assertOk();
        $response->assertSee($activePlan->name);
        $response->assertDontSee('Inactive Plan');
        $response->assertDontSee('Legacy Home Top Plan');
        $response->assertDontSee('Classic Banner Plan');
    }

    public function test_seller_creates_ad_request_using_pricing_plan_and_custom_price_duration_are_ignored(): void
    {
        Storage::fake('public');
        $this->withoutMiddleware();

        $seller = $this->createSeller();
        $product = $this->createProduct($seller->id);
        $plan = $this->createPricingPlan([
            'name' => 'Product Details Blast',
            'placement' => 'featured_products',
            'price' => 1000,
            'duration_days' => 3,
            'currency' => 'DZD',
        ]);

        $response = $this->actingAs($seller, 'seller')->post(route('vendor.ad-request.store'), [
            'title' => 'Plan Based Promotion',
            'product_id' => $product->id,
            'ad_pricing_plan_id' => $plan->id,
            'redirect_type' => 'product',
            'notes' => 'Please review this campaign.',
            'price' => 99999,
            'duration_days' => 99,
            'placement' => 'home_bottom',
            'ad_image' => UploadedFile::fake()->image('ad-banner.webp'),
            'payment_receipt' => UploadedFile::fake()->image('receipt.jpg'),
        ]);

        $response->assertRedirect();

        $adRequest = AdRequest::query()->latest('id')->first();

        $this->assertNotNull($adRequest);
        $this->assertSame($plan->id, $adRequest->ad_pricing_plan_id);
        $this->assertSame($plan->name, $adRequest->plan_name);
        $this->assertSame($plan->placement, $adRequest->placement);
        $this->assertSame($plan->duration_days, (int) $adRequest->duration_days);
        $this->assertSame($plan->duration_days, (int) $adRequest->plan_duration_days);
        $this->assertSame((float) $plan->price, (float) $adRequest->price);
        $this->assertSame((float) $plan->price, (float) $adRequest->plan_price);
        $this->assertSame('uploaded', $adRequest->payment_status);
    }

    public function test_payment_info_card_does_not_show_ad_price(): void
    {
        $this->withoutMiddleware();

        $seller = $this->createSeller();
        $plan = $this->createPricingPlan([
            'name' => 'Featured Banner',
            'price' => 1500,
            'placement' => 'featured_products',
        ]);

        $response = $this->actingAs($seller, 'seller')->get(route('vendor.vendor1.test'));

        $response->assertOk();
        $response->assertSee($plan->name);
        $response->assertSee('1,500.00', false);
        $response->assertDontSee('2,500.00', false);
    }

    public function test_seller_cannot_use_product_not_owned_by_him(): void
    {
        Storage::fake('public');
        $this->withoutMiddleware();

        $seller = $this->createSeller();
        $otherSeller = $this->createSeller('other@example.com');
        $otherProduct = $this->createProduct($otherSeller->id, 'Other Product');
        $plan = $this->createPricingPlan();

        $response = $this->actingAs($seller, 'seller')->from('/vendor/new-request')->post(route('vendor.ad-request.store'), [
            'title' => 'Unauthorized Product Promotion',
            'product_id' => $otherProduct->id,
            'ad_pricing_plan_id' => $plan->id,
            'ad_image' => UploadedFile::fake()->image('ad-banner.jpg'),
            'payment_receipt' => UploadedFile::fake()->image('receipt.jpg'),
        ]);

        $response->assertSessionHasErrors('product_id');
        $this->assertSame(0, AdRequest::query()->count());
    }

    public function test_admin_can_approve_ad_with_schedule(): void
    {
        $this->withoutMiddleware();

        $seller = $this->createSeller();
        $product = $this->createProduct($seller->id);
        $admin = $this->createAdmin();
        $plan = $this->createPricingPlan();
        $adRequest = $this->createAdRequest($seller->id, $product->id, $plan->id, [
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin, 'admin')->post(route('admin.ad-requests.approve', $adRequest->id), [
            'placement' => 'featured_products',
            'price' => 3300,
            'start_date' => now()->subHour()->format('Y-m-d H:i:s'),
            'end_date' => now()->addDays(7)->format('Y-m-d H:i:s'),
            'priority' => 5,
        ]);

        $response->assertRedirect();

        $adRequest->refresh();

        $this->assertContains($adRequest->status, ['approved', 'active']);
        $this->assertSame('featured_products', $adRequest->placement);
        $this->assertSame(3300.0, (float) $adRequest->price);
        $this->assertSame(5, (int) $adRequest->priority);
        $this->assertNotNull($adRequest->approved_at);
        $this->assertSame($admin->id, $adRequest->approved_by);
    }

    public function test_active_ads_api_includes_impression_url_and_click_url_without_sensitive_fields(): void
    {
        $this->withoutMiddleware();

        $seller = $this->createSeller();
        $product = $this->createProduct($seller->id);
        $plan = $this->createPricingPlan();

        $visible = $this->createAdRequest($seller->id, $product->id, $plan->id, [
            'title' => 'Visible Ad',
            'status' => 'active',
            'placement' => 'featured_products',
            'image_path' => 'ad-request/visible.jpg',
            'payment_receipt' => 'ad-request/receipts/visible.pdf',
            'start_date' => now()->subDay(),
            'end_date' => now()->addDay(),
        ]);

        $this->createAdRequest($seller->id, $product->id, $plan->id, [
            'title' => 'Pending Ad',
            'status' => 'pending',
            'placement' => 'featured_products',
            'image_path' => 'ad-request/pending.jpg',
            'start_date' => now()->subDay(),
            'end_date' => now()->addDay(),
        ]);

        $response = $this->getJson('/api/v1/ad-requests/active?placement=featured_products&limit=10');

        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonFragment([
            'id' => $visible->id,
            'title' => 'Visible Ad',
            'placement' => 'featured_products',
            'seller_id' => $seller->id,
            'type' => 'vendor_ad',
            'label' => 'Ad',
        ]);

        $payload = $response->json();
        $this->assertArrayHasKey('impression_url', $payload[0]);
        $this->assertArrayHasKey('click_url', $payload[0]);
        $this->assertArrayHasKey('visit_url', $payload[0]);
        $this->assertArrayNotHasKey('payment_receipt', $payload[0]);
        $this->assertArrayNotHasKey('admin_note', $payload[0]);
        $this->assertArrayNotHasKey('rejection_reason', $payload[0]);
    }

    public function test_web_visit_endpoint_increments_web_click_and_redirects_to_product(): void
    {
        $seller = $this->createSeller();
        $product = $this->createProduct($seller->id);
        $plan = $this->createPricingPlan();
        $adRequest = $this->createAdRequest($seller->id, $product->id, $plan->id, [
            'status' => 'active',
            'redirect_type' => null,
            'redirect_id' => null,
            'image_path' => 'ad-request/visible.jpg',
            'start_date' => now()->subDay(),
            'end_date' => now()->addDay(),
        ]);

        $response = $this->get(route('web.ad-requests.visit', $adRequest->id));

        $response->assertRedirect(route('product', $product->slug));
        $this->assertStringNotContainsString('/ad-requests/' . $adRequest->id . '/visit', (string) $response->headers->get('Location'));
        $response->assertSessionHas('ad_attribution.ad_request_id', $adRequest->id);
        $response->assertSessionHas('ad_attribution.product_id', $product->id);

        $adRequest->refresh();

        $this->assertSame(1, (int) $adRequest->clicks_web);
        $this->assertNotNull($adRequest->last_click_at);
    }

    public function test_visit_inactive_ad_does_not_increment_click_and_redirects_safely(): void
    {
        $this->withoutMiddleware();

        $seller = $this->createSeller();
        $product = $this->createProduct($seller->id);
        $plan = $this->createPricingPlan();
        $adRequest = $this->createAdRequest($seller->id, $product->id, $plan->id, [
            'status' => 'pending',
            'redirect_type' => 'product',
            'redirect_id' => $product->id,
            'image_path' => 'ad-request/pending.jpg',
            'start_date' => now()->subDay(),
            'end_date' => now()->addDay(),
        ]);

        $response = $this->get(route('web.ad-requests.visit', $adRequest->id));

        $response->assertRedirect(route('home'));

        $adRequest->refresh();

        $this->assertSame(0, (int) $adRequest->clicks_web);
    }

    public function test_active_ad_impression_app_increments_app_counter(): void
    {
        $this->withoutMiddleware();

        $seller = $this->createSeller();
        $product = $this->createProduct($seller->id);
        $plan = $this->createPricingPlan();
        $adRequest = $this->createAdRequest($seller->id, $product->id, $plan->id, [
            'status' => 'active',
            'image_path' => 'ad-request/visible.jpg',
            'start_date' => now()->subDay(),
            'end_date' => now()->addDay(),
        ]);

        $response = $this->postJson(route('api.v1.ad-requests.impression', $adRequest->id), [
            'source' => 'app',
        ]);

        $response->assertOk()->assertJson(['success' => true]);
        $adRequest->refresh();

        $this->assertSame(1, (int) $adRequest->impressions_app);
        $this->assertNotNull($adRequest->last_impression_at);
    }

    public function test_active_ad_click_app_increments_app_counter(): void
    {
        $this->withoutMiddleware();

        $seller = $this->createSeller();
        $product = $this->createProduct($seller->id);
        $plan = $this->createPricingPlan();
        $adRequest = $this->createAdRequest($seller->id, $product->id, $plan->id, [
            'status' => 'active',
            'image_path' => 'ad-request/visible.jpg',
            'start_date' => now()->subDay(),
            'end_date' => now()->addDay(),
        ]);

        $response = $this->postJson(route('api.v1.ad-requests.click', $adRequest->id), [
            'source' => 'app',
        ]);

        $response->assertOk()->assertJson(['success' => true]);
        $adRequest->refresh();

        $this->assertSame(1, (int) $adRequest->clicks_app);
        $this->assertNotNull($adRequest->last_click_at);
    }

    public function test_inactive_ad_tracking_does_not_increment(): void
    {
        $this->withoutMiddleware();

        $seller = $this->createSeller();
        $product = $this->createProduct($seller->id);
        $plan = $this->createPricingPlan();
        $adRequest = $this->createAdRequest($seller->id, $product->id, $plan->id, [
            'status' => 'pending',
            'image_path' => 'ad-request/pending.jpg',
            'start_date' => now()->subDay(),
            'end_date' => now()->addDay(),
        ]);

        $response = $this->postJson(route('api.v1.ad-requests.impression', $adRequest->id), [
            'source' => 'app',
        ]);

        $response->assertStatus(404);
        $adRequest->refresh();

        $this->assertSame(0, (int) $adRequest->impressions_app);
    }

    public function test_seller_show_page_displays_ad_stats(): void
    {
        $this->withoutMiddleware();

        $seller = $this->createSeller();
        $product = $this->createProduct($seller->id);
        $plan = $this->createPricingPlan(['name' => 'Stats Plan']);
        $adRequest = $this->createAdRequest($seller->id, $product->id, $plan->id, [
            'impressions_web' => 12,
            'impressions_app' => 8,
            'clicks_web' => 3,
            'clicks_app' => 2,
            'completed_purchases_count' => 4,
            'completed_purchases_amount' => 12500,
        ]);

        $response = $this->actingAs($seller, 'seller')->get(route('vendor.ad-request.show', $adRequest->id));

        $response->assertOk();
        $response->assertSee('Stats Plan');
        $response->assertSee('20');
        $response->assertSee('5');
        $response->assertSee('4');
        $response->assertSee('12,500.00', false);
    }

    public function test_featured_product_ad_partials_use_web_visit_link(): void
    {
        $defaultPartial = file_get_contents(resource_path('themes/default/web-views/partials/_featured-product-vendor-ads.blade.php'));
        $asterPartial = file_get_contents(resource_path('themes/theme_aster/theme-views/partials/_featured-product-vendor-ads.blade.php'));

        $this->assertStringContainsString("route('web.ad-requests.visit'", $defaultPartial);
        $this->assertStringContainsString("route('web.ad-requests.visit'", $asterPartial);
        $this->assertStringContainsString('product-single-hover', $defaultPartial);
        $this->assertStringContainsString('aspect-ratio: 1 / 1', $defaultPartial);
        $this->assertStringContainsString('class="product border rounded text-center d-flex flex-column gap-10 ov-hidden cursor-pointer"', $asterPartial);
        $this->assertStringContainsString('object-fit: cover;', $asterPartial);
    }

    public function test_completed_delivered_order_detail_from_ad_increments_purchase_stats(): void
    {
        $seller = $this->createSeller();
        $product = $this->createProduct($seller->id);
        $plan = $this->createPricingPlan();
        $adRequest = $this->createAdRequest($seller->id, $product->id, $plan->id, [
            'status' => 'active',
            'redirect_type' => 'product',
            'redirect_id' => $product->id,
        ]);
        $order = $this->createOrder($seller->id);
        $detail = $this->createOrderDetail($order->id, $product->id, $seller->id, [
            'ad_request_id' => $adRequest->id,
            'ad_attribution_source' => 'web',
            'price' => 1000,
            'qty' => 2,
            'tax' => 50,
            'discount' => 100,
        ]);

        app(AdRequestService::class)->recordCompletedPurchaseFromOrder($order);

        $adRequest->refresh();
        $detail->refresh();

        $this->assertSame(1, (int) $adRequest->completed_purchases_count);
        $this->assertSame(1950.0, (float) $adRequest->completed_purchases_amount);
        $this->assertNotNull($adRequest->last_purchase_at);
        $this->assertNotNull($detail->ad_purchase_counted_at);
    }

    public function test_completed_purchase_is_not_counted_twice(): void
    {
        $seller = $this->createSeller();
        $product = $this->createProduct($seller->id);
        $plan = $this->createPricingPlan();
        $adRequest = $this->createAdRequest($seller->id, $product->id, $plan->id, [
            'status' => 'active',
            'redirect_type' => 'product',
            'redirect_id' => $product->id,
        ]);
        $order = $this->createOrder($seller->id);

        $this->createOrderDetail($order->id, $product->id, $seller->id, [
            'ad_request_id' => $adRequest->id,
            'ad_attribution_source' => 'web',
            'price' => 500,
            'qty' => 1,
        ]);

        app(AdRequestService::class)->recordCompletedPurchaseFromOrder($order);
        app(AdRequestService::class)->recordCompletedPurchaseFromOrder($order);

        $adRequest->refresh();

        $this->assertSame(1, (int) $adRequest->completed_purchases_count);
        $this->assertSame(500.0, (float) $adRequest->completed_purchases_amount);
    }

    public function test_purchase_of_different_product_is_not_attributed_to_ad(): void
    {
        $seller = $this->createSeller();
        $advertisedProduct = $this->createProduct($seller->id, 'Advertised Product');
        $otherProduct = $this->createProduct($seller->id, 'Other Product');
        $plan = $this->createPricingPlan();
        $adRequest = $this->createAdRequest($seller->id, $advertisedProduct->id, $plan->id, [
            'status' => 'active',
            'redirect_type' => 'product',
            'redirect_id' => $advertisedProduct->id,
        ]);

        session()->put('ad_attribution', [
            'ad_request_id' => $adRequest->id,
            'product_id' => $advertisedProduct->id,
            'seller_id' => $seller->id,
            'shop_id' => $adRequest->shop_id,
            'clicked_at' => now()->toDateTimeString(),
        ]);

        $this->assertNull(app(AdRequestService::class)->resolveProductAttribution($otherProduct->id));
    }

    public function test_vendor_and_admin_show_pages_display_completed_purchase_stats(): void
    {
        $this->withoutMiddleware();

        $seller = $this->createSeller();
        $product = $this->createProduct($seller->id);
        $admin = $this->createAdmin();
        $plan = $this->createPricingPlan();
        $adRequest = $this->createAdRequest($seller->id, $product->id, $plan->id, [
            'completed_purchases_count' => 3,
            'completed_purchases_amount' => 12500,
            'last_purchase_at' => now(),
        ]);

        $vendorResponse = $this->actingAs($seller, 'seller')->get(route('vendor.ad-request.show', $adRequest->id));
        $adminResponse = $this->actingAs($admin, 'admin')->get(route('admin.ad-requests.show', $adRequest->id));

        $vendorResponse->assertOk()->assertSee('3')->assertSee('12,500.00', false);
        $adminResponse->assertOk()->assertSee('3')->assertSee('12,500.00', false);
    }

    public function test_banner_and_noest_routes_still_exist(): void
    {
        $bannerRoute = Route::getRoutes()->match(\Illuminate\Http\Request::create('/api/v1/banners', 'GET'));
        $noestRoute = Route::getRoutes()->match(\Illuminate\Http\Request::create('/api/v1/shipping-method/noest/wilayas', 'GET'));

        $this->assertSame('App\Http\Controllers\RestAPI\v1\BannerController@getBannerList', $bannerRoute->getActionName());
        $this->assertSame('App\Http\Controllers\RestAPI\v1\ShippingMethodController@noest_wilayas', $noestRoute->getActionName());
    }

    public function test_approved_featured_products_ad_does_not_appear_before_start_date(): void
    {
        $seller = $this->createSeller();
        $product = $this->createProduct($seller->id);
        $plan = $this->createPricingPlan(['placement' => 'featured_products']);

        $this->createAdRequest($seller->id, $product->id, $plan->id, [
            'status' => 'approved',
            'placement' => 'featured_products',
            'image_path' => 'ad-request/future.jpg',
            'start_date' => now()->addDay(),
            'end_date' => now()->addDays(2),
        ]);

        $response = $this->getJson('/api/v1/ad-requests/active?placement=featured_products');

        $response->assertOk()->assertJsonCount(0);
    }

    public function test_approved_featured_products_ad_does_not_appear_after_end_date(): void
    {
        $seller = $this->createSeller();
        $product = $this->createProduct($seller->id);
        $plan = $this->createPricingPlan(['placement' => 'featured_products']);

        $this->createAdRequest($seller->id, $product->id, $plan->id, [
            'status' => 'approved',
            'placement' => 'featured_products',
            'image_path' => 'ad-request/past.jpg',
            'start_date' => now()->subDays(3),
            'end_date' => now()->subDay(),
        ]);

        $response = $this->getJson('/api/v1/ad-requests/active?placement=featured_products');

        $response->assertOk()->assertJsonCount(0);
    }

    public function test_home_banner_classic_is_admin_only(): void
    {
        $seller = $this->createSeller();
        $this->createPricingPlan(['name' => 'Classic Home Banner', 'placement' => 'home_banner_classic', 'status' => true]);

        $response = $this->actingAs($seller, 'seller')->get(route('vendor.vendor1.test'));

        $response->assertOk();
        $response->assertDontSee('Classic Home Banner');
    }

    private function setUpDatabase(): void
    {
        Schema::create('business_settings', function (Blueprint $table) {
            $table->id();
            $table->string('type')->nullable();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        DB::table('business_settings')->insert([
            ['type' => 'language', 'value' => json_encode([['code' => 'en', 'name' => 'English', 'default' => true, 'direction' => 'ltr', 'status' => 1]]), 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'pagination_limit', 'value' => '15', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'system_default_currency', 'value' => '1', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'ad_default_price', 'value' => '2500', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'ad_currency', 'value' => 'DZD', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'ad_receipt_required', 'value' => '1', 'created_at' => now(), 'updated_at' => now()],
        ]);

        Schema::create('sellers', function (Blueprint $table) {
            $table->id();
            $table->string('f_name');
            $table->string('l_name')->nullable();
            $table->string('country_code')->nullable();
            $table->string('phone')->nullable();
            $table->string('image')->nullable();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('status')->default('approved');
            $table->string('pos_status')->default('1');
            $table->timestamps();
        });

        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_id')->nullable();
            $table->string('name')->nullable();
            $table->string('slug')->nullable();
            $table->string('image')->nullable();
            $table->longText('setup_guide')->nullable();
            $table->boolean('temporary_close')->default(false);
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('symbol')->nullable();
            $table->string('code')->nullable();
            $table->decimal('exchange_rate', 12, 2)->default(1);
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        DB::table('currencies')->insert([
            'id' => 1,
            'name' => 'Algerian Dinar',
            'symbol' => 'DZD',
            'code' => 'DZD',
            'exchange_rate' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('added_by')->default('seller');
            $table->string('name');
            $table->string('slug')->nullable();
            $table->integer('status')->default(1);
            $table->integer('request_status')->default(1);
            $table->timestamps();
        });

        Schema::create('translations', function (Blueprint $table) {
            $table->id();
            $table->string('translationable_type')->nullable();
            $table->unsignedBigInteger('translationable_id')->nullable();
            $table->string('locale')->nullable();
            $table->string('key')->nullable();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('delivery_man_id')->nullable();
            $table->timestamps();
        });

        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('storages', function (Blueprint $table) {
            $table->id();
            $table->string('data_type');
            $table->unsignedBigInteger('data_id');
            $table->string('key');
            $table->string('value')->nullable();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('sent_by')->nullable();
            $table->string('sent_to')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('ad_request_id')->nullable();
            $table->string('type')->nullable();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->integer('notification_count')->default(0);
            $table->string('image')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('notification_seens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('notification_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
        });

        Schema::create('chattings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('delivery_man_id')->nullable();
            $table->string('sent_by')->nullable();
            $table->boolean('seen_by_seller')->default(false);
            $table->boolean('seen_by_admin')->default(false);
            $table->boolean('seen_by_customer')->default(false);
            $table->text('message')->nullable();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('seller_is')->nullable();
            $table->unsignedBigInteger('seller_id')->nullable();
            $table->string('order_status')->nullable();
            $table->timestamps();
        });

        Schema::create('guest_users', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address')->nullable();
            $table->string('fcm_token')->nullable();
            $table->timestamps();
        });

        Schema::create('order_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('seller_id')->nullable();
            $table->longText('product_details')->nullable();
            $table->integer('qty')->default(1);
            $table->decimal('price', 14, 2)->default(0);
            $table->decimal('tax', 14, 2)->default(0);
            $table->decimal('discount', 14, 2)->default(0);
            $table->string('tax_model')->nullable();
            $table->string('delivery_status')->nullable();
            $table->string('payment_status')->nullable();
            $table->unsignedBigInteger('shipping_method_id')->nullable();
            $table->string('variant')->nullable();
            $table->text('variation')->nullable();
            $table->string('discount_type')->nullable();
            $table->unsignedBigInteger('ad_request_id')->nullable();
            $table->string('ad_attribution_source')->nullable();
            $table->dateTime('ad_purchase_counted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('refund_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });

        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->boolean('seen')->default(false);
            $table->timestamps();
        });

        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('status')->nullable();
            $table->timestamps();
        });

        Schema::create('seller_commission_alert_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_id')->nullable();
            $table->string('recipient_type')->nullable();
            $table->timestamps();
        });

        Schema::create('ad_pricing_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('placement');
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->integer('duration_days')->default(1);
            $table->string('currency', 20)->default('DZD');
            $table->boolean('status')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('ad_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vendor_id');
            $table->unsignedBigInteger('shop_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('ad_pricing_plan_id')->nullable();
            $table->string('plan_name')->nullable();
            $table->decimal('plan_price', 12, 2)->nullable();
            $table->integer('plan_duration_days')->nullable();
            $table->string('plan_currency', 20)->nullable();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('ad_type');
            $table->string('placement')->nullable()->default('featured_products');
            $table->integer('duration_days');
            $table->decimal('price', 12, 2)->default(0);
            $table->string('image_path')->nullable();
            $table->text('notes')->nullable();
            $table->string('redirect_type')->nullable();
            $table->unsignedBigInteger('redirect_id')->nullable();
            $table->string('redirect_url')->nullable();
            $table->string('payment_receipt')->nullable();
            $table->string('payment_receipt_storage_type')->nullable()->default('public');
            $table->string('payment_status')->nullable()->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->text('admin_note')->nullable();
            $table->dateTime('start_date')->nullable();
            $table->dateTime('end_date')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->dateTime('rejected_at')->nullable();
            $table->unsignedBigInteger('rejected_by')->nullable();
            $table->integer('priority')->default(0);
            $table->boolean('is_paid')->default(false);
            $table->unsignedBigInteger('impressions_web')->default(0);
            $table->unsignedBigInteger('impressions_app')->default(0);
            $table->unsignedBigInteger('clicks_web')->default(0);
            $table->unsignedBigInteger('clicks_app')->default(0);
            $table->dateTime('last_impression_at')->nullable();
            $table->dateTime('last_click_at')->nullable();
            $table->unsignedBigInteger('completed_purchases_count')->default(0);
            $table->decimal('completed_purchases_amount', 14, 2)->default(0);
            $table->dateTime('last_purchase_at')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }

    private function createSeller(string $email = 'seller@example.com'): Seller
    {
        $seller = Seller::query()->create([
            'f_name' => 'Seller',
            'l_name' => 'One',
            'email' => $email,
            'phone' => '0555000000',
            'password' => bcrypt('password'),
            'status' => 'approved',
            'pos_status' => 1,
        ]);

        DB::table('shops')->insert([
            'seller_id' => $seller->id,
            'name' => 'Seller Shop ' . $seller->id,
            'slug' => 'seller-shop-' . $seller->id,
            'temporary_close' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $seller->fresh();
    }

    private function createAdmin(): Admin
    {
        return Admin::query()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'status' => true,
        ]);
    }

    private function createProduct(int $sellerId, string $name = 'Seller Product')
    {
        $id = DB::table('products')->insertGetId([
            'user_id' => $sellerId,
            'added_by' => 'seller',
            'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name) . '-' . uniqid(),
            'status' => 1,
            'request_status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('products')->where('id', $id)->first();
    }

    private function createOrder(int $sellerId, array $overrides = []): Order
    {
        return Order::query()->create(array_merge([
            'seller_id' => $sellerId,
            'seller_is' => 'seller',
            'order_status' => 'delivered',
        ], $overrides));
    }

    private function createOrderDetail(int $orderId, int $productId, int $sellerId, array $overrides = []): OrderDetail
    {
        return OrderDetail::query()->create(array_merge([
            'order_id' => $orderId,
            'product_id' => $productId,
            'seller_id' => $sellerId,
            'product_details' => '{}',
            'qty' => 1,
            'price' => 100,
            'tax' => 0,
            'discount' => 0,
            'tax_model' => 'exclude',
            'delivery_status' => 'delivered',
            'payment_status' => 'paid',
        ], $overrides));
    }

    private function createPricingPlan(array $overrides = []): AdPricingPlan
    {
        $defaults = [
            'name' => 'Featured Weekly',
            'placement' => 'featured_products',
            'description' => 'Featured products package',
            'price' => 2500,
            'duration_days' => 7,
            'currency' => 'DZD',
            'status' => true,
            'sort_order' => 0,
        ];

        return AdPricingPlan::query()->create(array_merge($defaults, $overrides));
    }

    private function createAdRequest(int $sellerId, ?int $productId = null, ?int $planId = null, array $overrides = []): AdRequest
    {
        $shopId = DB::table('shops')->where('seller_id', $sellerId)->value('id');
        $plan = $planId ? AdPricingPlan::query()->find($planId) : $this->createPricingPlan();

        $defaults = [
            'vendor_id' => $sellerId,
            'shop_id' => $shopId,
            'product_id' => $productId,
            'ad_pricing_plan_id' => $plan?->id,
            'plan_name' => $plan?->name,
            'plan_price' => $plan?->price,
            'plan_duration_days' => $plan?->duration_days,
            'plan_currency' => $plan?->currency,
            'title' => 'Sample Ad Request',
            'description' => 'Ad description',
            'ad_type' => 'banner',
            'placement' => $plan?->placement ?? 'featured_products',
            'duration_days' => $plan?->duration_days ?? 7,
            'price' => $plan?->price ?? 2500,
            'image_path' => 'ad-request/sample.jpg',
            'notes' => 'Sample notes',
            'payment_receipt' => 'ad-request/receipts/sample.pdf',
            'payment_status' => 'uploaded',
            'status' => 'pending',
            'priority' => 0,
            'is_paid' => false,
            'impressions_web' => 0,
            'impressions_app' => 0,
            'clicks_web' => 0,
            'clicks_app' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        return AdRequest::query()->create(array_merge($defaults, $overrides));
    }
}
