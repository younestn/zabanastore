<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Seller;
use App\Models\Shop;
use App\Services\SellerBadgeService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use Tests\TestCase;

class SellerBadgeServiceTest extends TestCase
{
    private SellerBadgeService $sellerBadgeService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resetSellerBadgeServiceCache();
        $this->setUpDatabase();
        $this->sellerBadgeService = app(SellerBadgeService::class);
    }

    protected function tearDown(): void
    {
        foreach ([
            'seller_badge_histories',
            'seller_badges',
            'reviews',
            'order_transactions',
            'orders',
            'products',
            'translations',
            'shops',
            'sellers',
            'storages',
            'business_settings',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        parent::tearDown();
    }

    public function test_seller_without_products_does_not_get_new_seller_badge(): void
    {
        $seller = $this->createSeller();

        $this->assertNull($this->sellerBadgeService->getFormattedBadgeForSeller($seller));
    }

    public function test_seller_with_first_published_product_gets_new_seller_badge(): void
    {
        $seller = $this->createSeller();
        $this->createShop($seller);
        $this->createProduct($seller);

        $badge = $this->sellerBadgeService->recalculateSellerBadge($seller);

        $this->assertSame('new_seller', $badge?->badge_key);
        $this->assertSame('new_seller', $this->sellerBadgeService->getFormattedBadgeForSeller($seller)['key']);
    }

    public function test_low_score_seller_does_not_get_trusted_or_elite_badge(): void
    {
        $seller = $this->createSeller();
        $this->createShop($seller);
        $product = $this->createProduct($seller);

        $this->createOrders($seller, delivered: 2, cancelled: 8);
        $this->createReviews($product, rating: 2, count: 3);

        $badge = $this->sellerBadgeService->recalculateSellerBadge($seller);

        $this->assertNotContains($badge?->badge_key, ['trusted_seller', 'elite_seller']);
    }

    public function test_high_score_seller_gets_elite_badge(): void
    {
        $seller = $this->createSeller(createdAt: now()->subDays(220));
        $this->createShop($seller, verified: true);
        $product = $this->createProduct($seller);

        $this->createOrders($seller, delivered: 60, cancelled: 0);
        $this->createReviews($product, rating: 5, count: 10);

        $badge = $this->sellerBadgeService->recalculateSellerBadge($seller);

        $this->assertSame('elite_seller', $badge?->badge_key);
    }

    public function test_manual_override_prevents_automatic_recalculation_from_changing_current_badge(): void
    {
        $seller = $this->createSeller(createdAt: now()->subDays(220));
        $this->createShop($seller, verified: true);
        $product = $this->createProduct($seller);
        $this->createOrders($seller, delivered: 60, cancelled: 0);
        $this->createReviews($product, rating: 5, count: 10);

        $this->sellerBadgeService->applyManualBadge($seller, 'verified_seller', 'Admin verification review');
        $badge = $this->sellerBadgeService->recalculateSellerBadge($seller);

        $this->assertSame('verified_seller', $badge->badge_key);
        $this->assertSame('elite_seller', $badge->auto_badge_key);
        $this->assertTrue((bool)$badge->manual_override);
    }

    public function test_restore_automatic_badge_returns_to_auto_calculation(): void
    {
        $seller = $this->createSeller(createdAt: now()->subDays(220));
        $this->createShop($seller, verified: true);
        $product = $this->createProduct($seller);
        $this->createOrders($seller, delivered: 60, cancelled: 0);
        $this->createReviews($product, rating: 5, count: 10);

        $this->sellerBadgeService->applyManualBadge($seller, 'verified_seller', 'Admin verification review');
        $badge = $this->sellerBadgeService->restoreAutomaticBadge($seller);

        $this->assertSame('elite_seller', $badge?->badge_key);
        $this->assertFalse((bool)$badge?->manual_override);
    }

    public function test_vendor_list_view_contains_compliance_score_and_badge_columns(): void
    {
        $view = file_get_contents(resource_path('views/admin-views/vendor/index.blade.php'));

        $this->assertStringContainsString('compliance_score', $view);
        $this->assertStringContainsString('seller_badge', $view);
    }

    public function test_vendor_detail_view_contains_verification_evaluation_button(): void
    {
        $view = file_get_contents(resource_path('views/admin-views/vendor/view.blade.php'));

        $this->assertStringContainsString('verification_evaluation', $view);
    }

    public function test_product_api_serialization_has_seller_badge_field(): void
    {
        $seller = $this->createSeller();
        $this->createShop($seller);
        $product = $this->createProduct($seller);
        $this->sellerBadgeService->recalculateSellerBadge($seller);

        $this->assertSame('new_seller', $product->seller_badge['key']);
        $this->assertArrayHasKey('seller_badge', $product->toArray());
    }

    public function test_null_seller_badge_is_safe_for_admin_products(): void
    {
        $product = Product::query()->create([
            'user_id' => 0,
            'added_by' => 'admin',
            'name' => 'Admin product',
            'colors' => json_encode([]),
            'category_ids' => json_encode([]),
            'attributes' => json_encode([]),
            'choice_options' => json_encode([]),
            'variation' => json_encode([]),
            'status' => 1,
            'request_status' => 1,
        ]);

        $this->assertNull($product->seller_badge);
    }

    private function createSeller(?Carbon $createdAt = null): Seller
    {
        $seller = new Seller([
            'f_name' => 'Test',
            'l_name' => 'Seller',
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->unique()->numerify('100000####'),
            'image' => 'seller.png',
            'status' => 'approved',
            'minimum_order_amount' => 0,
            'stock_limit' => 0,
        ]);
        $seller->created_at = $createdAt ?? now();
        $seller->updated_at = $createdAt ?? now();
        $seller->save();

        return $seller;
    }

    private function createShop(Seller $seller, bool $verified = false): Shop
    {
        return Shop::query()->create([
            'seller_id' => $seller->id,
            'author_type' => 'seller',
            'name' => 'Test Shop',
            'slug' => 'test-shop-' . $seller->id,
            'address' => 'Rabat',
            'contact' => '1000000000',
            'image' => 'shop.png',
            'tax_identification_number' => $verified ? 'TIN-100' : null,
            'tin_certificate' => $verified ? 'tin.pdf' : null,
            'tin_expire_date' => $verified ? now()->addYear()->toDateString() : null,
        ]);
    }

    private function createProduct(Seller $seller): Product
    {
        return Product::query()->create([
            'user_id' => $seller->id,
            'seller_id' => $seller->id,
            'added_by' => 'seller',
            'name' => 'Seller product',
            'colors' => json_encode([]),
            'category_ids' => json_encode([]),
            'attributes' => json_encode([]),
            'choice_options' => json_encode([]),
            'variation' => json_encode([]),
            'status' => 1,
            'request_status' => 1,
        ]);
    }

    private function createOrders(Seller $seller, int $delivered, int $cancelled): void
    {
        for ($index = 0; $index < $delivered; $index++) {
            DB::table('orders')->insert([
                'seller_id' => $seller->id,
                'seller_is' => 'seller',
                'order_status' => 'delivered',
                'order_type' => 'default_type',
                'order_amount' => 100,
                'expected_delivery_date' => now()->addDay()->toDateString(),
                'created_at' => now()->subDays(10),
                'updated_at' => now(),
            ]);
        }

        for ($index = 0; $index < $cancelled; $index++) {
            DB::table('orders')->insert([
                'seller_id' => $seller->id,
                'seller_is' => 'seller',
                'order_status' => 'canceled',
                'order_type' => 'default_type',
                'order_amount' => 100,
                'expected_delivery_date' => now()->addDay()->toDateString(),
                'created_at' => now()->subDays(10),
                'updated_at' => now(),
            ]);
        }
    }

    private function createReviews(Product $product, int $rating, int $count): void
    {
        for ($index = 0; $index < $count; $index++) {
            DB::table('reviews')->insert([
                'product_id' => $product->id,
                'rating' => $rating,
                'status' => 1,
                'delivery_man_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function setUpDatabase(): void
    {
        $this->dropTables();

        Schema::create('business_settings', function (Blueprint $table) {
            $table->id();
            $table->string('type')->nullable()->index();
            $table->text('value')->nullable();
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

        Schema::create('sellers', function (Blueprint $table) {
            $table->id();
            $table->string('f_name')->nullable();
            $table->string('l_name')->nullable();
            $table->string('country_code')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->string('image')->nullable();
            $table->string('status')->default('approved');
            $table->string('seller_commission_type')->nullable();
            $table->decimal('seller_commission_value', 8, 2)->nullable();
            $table->decimal('sales_commission_percentage', 8, 2)->nullable();
            $table->decimal('minimum_order_amount', 8, 2)->default(0);
            $table->integer('stock_limit')->default(0);
            $table->timestamps();
        });

        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_id')->nullable();
            $table->string('author_type')->default('seller');
            $table->string('name')->nullable();
            $table->string('slug')->nullable();
            $table->string('address')->nullable();
            $table->string('contact')->nullable();
            $table->string('image')->nullable();
            $table->string('image_storage_type')->nullable();
            $table->string('banner')->nullable();
            $table->string('banner_storage_type')->nullable();
            $table->string('bottom_banner')->nullable();
            $table->string('bottom_banner_storage_type')->nullable();
            $table->string('offer_banner')->nullable();
            $table->string('offer_banner_storage_type')->nullable();
            $table->boolean('temporary_close')->default(false);
            $table->boolean('vacation_status')->default(false);
            $table->string('tax_identification_number')->nullable();
            $table->date('tin_expire_date')->nullable();
            $table->string('tin_certificate')->nullable();
            $table->string('tin_certificate_storage_type')->nullable();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('seller_id')->nullable();
            $table->string('added_by')->nullable();
            $table->string('name')->nullable();
            $table->text('colors')->nullable();
            $table->text('category_ids')->nullable();
            $table->text('attributes')->nullable();
            $table->text('choice_options')->nullable();
            $table->text('variation')->nullable();
            $table->boolean('status')->default(true);
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

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_id')->nullable();
            $table->string('seller_is')->nullable();
            $table->string('order_status')->nullable();
            $table->string('order_type')->nullable();
            $table->decimal('order_amount', 12, 2)->default(0);
            $table->date('expected_delivery_date')->nullable();
            $table->timestamps();
        });

        Schema::create('order_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_id')->nullable();
            $table->string('seller_is')->nullable();
            $table->decimal('seller_amount', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('delivery_man_id')->nullable();
            $table->integer('rating')->default(0);
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('seller_badges', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_id')->unique();
            $table->string('badge_key')->nullable();
            $table->string('auto_badge_key')->nullable();
            $table->string('manual_badge_key')->nullable();
            $table->boolean('manual_override')->default(false);
            $table->text('manual_override_reason')->nullable();
            $table->decimal('compliance_score', 5, 2)->default(0);
            $table->unsignedTinyInteger('badge_level')->nullable();
            $table->timestamp('recalculated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('seller_badge_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_id')->index();
            $table->string('old_badge_key')->nullable();
            $table->string('new_badge_key')->nullable();
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->string('change_type')->default('automatic');
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    private function dropTables(): void
    {
        foreach ([
            'seller_badge_histories',
            'seller_badges',
            'reviews',
            'order_transactions',
            'orders',
            'products',
            'translations',
            'shops',
            'sellers',
            'storages',
            'business_settings',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }

    private function resetSellerBadgeServiceCache(): void
    {
        $reflection = new ReflectionClass(SellerBadgeService::class);

        foreach (['tableExists', 'columnExists', 'formattedBadgeCache'] as $propertyName) {
            $property = $reflection->getProperty($propertyName);
            $property->setAccessible(true);
            $property->setValue(null, []);
        }
    }
}
