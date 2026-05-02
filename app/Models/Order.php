<?php

namespace App\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $customer_id
 * @property bool $is_guest
 * @property string $customer_type
 * @property string $payment_status
 * @property string $order_status
 * @property string $payment_method
 * @property string $transaction_ref
 * @property string $payment_by
 * @property string $payment_note
 * @property float $order_amount
 * @property float $paid_amount
 * @property float $bring_change_amount
 * @property string $bring_change_amount_currency
 * @property float $admin_commission
 * @property bool $is_pause
 * @property string $cause
 * @property string $shipping_address
 * @property \DateTime $created_at
 * @property \DateTime $updated_at
 * @property float $discount_amount
 * @property string $discount_type
 * @property string $coupon_code
 * @property string $coupon_discount_bearer
 * @property string $shipping_responsibility
 * @property int $shipping_method_id
 * @property float $shipping_cost
 * @property bool $is_shipping_free
 * @property string $order_group_id
 * @property string $verification_code
 * @property bool $verification_status
 * @property int $seller_id
 * @property string $seller_is
 * @property object $shipping_address_data
 * @property int $delivery_man_id
 * @property Carbon|null $deliveryman_assigned_at
 * @property float $deliveryman_charge
 * @property \DateTime $expected_delivery_date
 * @property string $order_note
 * @property int $billing_address
 * @property object $billing_address_data
 * @property string $order_type
 * @property float $extra_discount
 * @property string $extra_discount_type
 * @property float $refer_and_earn_discount
 * @property string $free_delivery_bearer
 * @property bool $checked
 * @property string $shipping_type
 * @property string $delivery_type
 * @property string $delivery_service_name
 * @property string $third_party_delivery_tracking_id
 */
class Order extends Model
{

    protected $fillable = [
        'id',
        'customer_id',
        'is_guest',
        'customer_type',
        'payment_status',
        'order_status',
        'payment_method',
        'transaction_ref',
        'payment_by',
        'payment_note',
        'order_amount',
        'paid_amount',
        'bring_change_amount',
        'bring_change_amount_currency',
        'admin_commission',
        'is_pause',
        'cause',
        'shipping_address',
        'discount_type',
        'discount_amount',
        'coupon_code',
        'coupon_discount_bearer',
        'shipping_responsibility',
        'shipping_method_id',
        'shipping_cost',
        'is_shipping_free',
        'order_group_id',
        'verification_code',
        'verification_status',
        'seller_id',
        'seller_is',
        'shipping_address_data',
        'delivery_man_id',
        'deliveryman_assigned_at',
        'deliveryman_charge',
        'expected_delivery_date',
        'order_note',
        'billing_address',
        'billing_address_data',
        'order_type',
        'extra_discount',
        'extra_discount_type',
        'refer_and_earn_discount',
        'free_delivery_bearer',
        'checked',
        'shipping_type',
        'delivery_type',
        'delivery_service_name',
        'third_party_delivery_tracking_id',
    ];

    protected $casts = [
        'customer_id' => 'integer',
        'is_guest' => 'boolean',
        'customer_type' => 'string',
        'payment_status' => 'string',
        'order_status' => 'string',
        'payment_method' => 'string',
        'transaction_ref' => 'string',
        'payment_by' => 'string',
        'payment_note' => 'string',
        'order_amount' => 'double',
        'refer_and_earn_discount' => 'double',
        'paid_amount' => 'double',
        'bring_change_amount' => 'double',
        'bring_change_amount_currency' => 'string',
        'admin_commission' => 'decimal:2',
        'is_pause' => 'boolean',
        'cause' => 'string',
        'shipping_address' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'discount_amount' => 'double',
        'discount_type' => 'string',
        'coupon_code' => 'string',
        'coupon_discount_bearer' => 'string',
        'shipping_responsibility' => 'string',
        'shipping_method_id' => 'integer',
        'shipping_cost' => 'double',
        'is_shipping_free' => 'boolean',
        'order_group_id' => 'string',
        'verification_code' => 'string',
        'verification_status' => 'boolean',
        'seller_id' => 'integer',
        'seller_is' => 'string',
        'shipping_address_data' => 'object',
        'delivery_man_id' => 'integer',
        'deliveryman_assigned_at' => 'datetime',
        'deliveryman_charge' => 'double',
        'order_note' => 'string',
        'billing_address' => 'integer',
        'billing_address_data' => 'object',
        'order_type' => 'string',
        'extra_discount' => 'double',
        'extra_discount_type' => 'string',
        'free_delivery_bearer' => 'string',
        'checked' => 'boolean',
        'shipping_type' => 'string',
        'delivery_type' => 'string',
        'delivery_service_name' => 'string',
        'third_party_delivery_tracking_id' => 'string',
    ];


    public function details(): HasMany
    {
        return $this->hasMany(OrderDetail::class)->orderBy('seller_id', 'ASC');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function sellerName(): HasOne
    {
        return $this->hasOne(OrderDetail::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function shipping(): BelongsTo
{
    return $this->belongsTo(ShippingMethod::class, 'shipping_method_id')
        ->withDefault(function ($shipping, $order) {
            $label = ((int)($order->shipping_method_id ?? 0) === 0)
                ? translate('NOEST_shipping')
                : translate('shipping');

            $shipping->title = $label;
            $shipping->method_name = $label;
            $shipping->name = $label;
        });
}
    public function shippingAddress(): BelongsTo
    {
        return $this->belongsTo(ShippingAddress::class, 'shipping_address');
    }

    public function billingAddress(): BelongsTo
    {
        return $this->belongsTo(ShippingAddress::class, 'billing_address');
    }

    public function deliveryMan(): BelongsTo
    {
        return $this->belongsTo(DeliveryMan::class, 'delivery_man_id');
    }

    /* delivery_man_review -> deliveryManReview */
    public function deliveryManReview(): HasOne
    {
        return $this->hasOne(Review::class, 'order_id')->whereNotNull('delivery_man_id');
    }

    /* order_transaction -> orderTransaction */
    public function orderTransaction(): HasOne
    {
        return $this->hasOne(OrderTransaction::class, 'order_id');
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class, 'coupon_code', 'code');
    }

    /* order_status_history -> orderStatusHistory */
    public function orderStatusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    /* order_details -> orderDetails */
    public function orderDetails(): HasMany
    {
        return $this->hasMany(OrderDetail::class, 'order_id');
    }

    public function refundRequest(): HasOne
    {
        return $this->hasOne(RefundRequest::class, 'order_id');
    }
    /* offline_payments -> offlinePayments */
    public function offlinePayments(): BelongsTo
    {
        return $this->belongsTo(OfflinePayments::class, 'id', 'order_id');
    }

    /* verification_images -> verificationImages */
    public function verificationImages(): HasMany
    {
        return $this->hasMany(OrderDeliveryVerification::class, 'order_id');
    }


    protected static function boot(): void
    {
        parent::boot();
        //static::addGlobalScope(new RememberScope);

        static::updated(function (Order $order): void {
            if ($order->wasChanged('order_status') && $order->order_status === 'delivered') {
                app(\App\Services\AdRequestService::class)->recordCompletedPurchaseFromOrder($order);
            }
        });
    }

    public function getShippingDisplayNameAttribute(): string
{
    if ((int)($this->shipping_method_id ?? 0) === 0) {
        return translate('NOEST_shipping');
    }

    return $this->shipping?->title
        ?? $this->shipping?->method_name
        ?? $this->shipping?->name
        ?? translate('shipping');
}





private static function normalizeTrustScorePhone(?string $phone): ?string
{
    if (empty($phone)) {
        return null;
    }

    $digits = preg_replace('/\D+/', '', $phone);

    if (empty($digits)) {
        return null;
    }

    return strlen($digits) >= 9 ? substr($digits, -9) : $digits;
}

private static function buildTrustScorePhoneCandidates(?string $phone): array
{
    $normalized = self::normalizeTrustScorePhone($phone);
    $digits = preg_replace('/\D+/', '', (string)$phone);

    $candidates = array_filter([
        trim((string)$phone),
        $digits,
        $normalized,
        $normalized ? '0' . $normalized : null,
        $normalized ? '213' . $normalized : null,
        $normalized ? '+213' . $normalized : null,
    ]);

    return array_values(array_unique($candidates));
}

private static function decodeTrustScoreAddressData($value): array
{
    if (is_array($value)) {
        return $value;
    }

    if (is_object($value)) {
        return (array)$value;
    }

    if (blank($value)) {
        return [];
    }

    $decoded = json_decode($value, true);

    return is_array($decoded) ? $decoded : [];
}

private static function applyPhoneLikeConditions($query, array $candidates, string $column, string $boolean = 'and'): void
{
    $method = $boolean === 'or' ? 'orWhere' : 'where';

    $query->{$method}(function ($subQuery) use ($candidates, $column) {
        foreach ($candidates as $candidate) {
            $subQuery->orWhere($column, 'like', '%' . $candidate . '%');
        }
    });
}

private static function extractPhonesForTrustScore(self $order, array $addressPhoneMap = []): array
{
    $phones = [];

    if (!empty($order?->customer?->phone)) {
        $phones[] = $order->customer->phone;
    }

    if (!empty($order->shipping_address) && isset($addressPhoneMap[$order->shipping_address])) {
        $phones[] = $addressPhoneMap[$order->shipping_address];
    }

    if (!empty($order->billing_address) && isset($addressPhoneMap[$order->billing_address])) {
        $phones[] = $addressPhoneMap[$order->billing_address];
    }

    $shippingAddressData = self::decodeTrustScoreAddressData($order->shipping_address_data ?? null);
    $billingAddressData = self::decodeTrustScoreAddressData($order->billing_address_data ?? null);

    if (!empty($shippingAddressData['phone'])) {
        $phones[] = $shippingAddressData['phone'];
    }

    if (!empty($billingAddressData['phone'])) {
        $phones[] = $billingAddressData['phone'];
    }

    return array_values(array_unique(array_filter($phones)));
}

public static function getCustomerTrustScoreByPhone(?string $phone): array
{
    $normalizedPhone = self::normalizeTrustScorePhone($phone);
    $phoneCandidates = self::buildTrustScorePhoneCandidates($phone);

    if (!$normalizedPhone || empty($phoneCandidates)) {
        return [
            'phone' => $phone,
            'score' => 0,
            'delivered' => 0,
            'resolved_orders' => 0,
            'has_history' => false,
            'label' => 'لا يوجد سجل محسوم لهذا الرقم عبر جميع متاجر المنصة بعد',
        ];
    }

    $terminalStatuses = ['delivered', 'returned', 'canceled', 'failed'];

    $hasShippingAddressColumn = \Illuminate\Support\Facades\Schema::hasColumn('orders', 'shipping_address');
    $hasBillingAddressColumn = \Illuminate\Support\Facades\Schema::hasColumn('orders', 'billing_address');
    $hasShippingAddressDataColumn = \Illuminate\Support\Facades\Schema::hasColumn('orders', 'shipping_address_data');
    $hasBillingAddressDataColumn = \Illuminate\Support\Facades\Schema::hasColumn('orders', 'billing_address_data');

    $hasShippingAddressesTable = \Illuminate\Support\Facades\Schema::hasTable('shipping_addresses')
        && \Illuminate\Support\Facades\Schema::hasColumn('shipping_addresses', 'id')
        && \Illuminate\Support\Facades\Schema::hasColumn('shipping_addresses', 'phone');

    $orders = self::query()
        ->with('customer')
        ->whereIn('order_status', $terminalStatuses)
        ->where(function ($query) use (
            $phoneCandidates,
            $hasShippingAddressColumn,
            $hasBillingAddressColumn,
            $hasShippingAddressDataColumn,
            $hasBillingAddressDataColumn,
            $hasShippingAddressesTable
        ) {
            $query->whereHas('customer', function ($customerQuery) use ($phoneCandidates) {
                self::applyPhoneLikeConditions($customerQuery, $phoneCandidates, 'phone');
            });

            if ($hasShippingAddressDataColumn) {
                self::applyPhoneLikeConditions($query, $phoneCandidates, 'shipping_address_data', 'or');
            }

            if ($hasBillingAddressDataColumn) {
                self::applyPhoneLikeConditions($query, $phoneCandidates, 'billing_address_data', 'or');
            }

            if ($hasShippingAddressesTable && $hasShippingAddressColumn) {
                $query->orWhereIn('shipping_address', function ($addressQuery) use ($phoneCandidates) {
                    $addressQuery->from('shipping_addresses')->select('id');
                    self::applyPhoneLikeConditions($addressQuery, $phoneCandidates, 'phone');
                });
            }

            if ($hasShippingAddressesTable && $hasBillingAddressColumn) {
                $query->orWhereIn('billing_address', function ($addressQuery) use ($phoneCandidates) {
                    $addressQuery->from('shipping_addresses')->select('id');
                    self::applyPhoneLikeConditions($addressQuery, $phoneCandidates, 'phone');
                });
            }
        })
        ->get();

    $addressPhoneMap = [];

    if ($hasShippingAddressesTable && $orders->isNotEmpty()) {
        $addressIds = $orders->pluck('shipping_address')
            ->merge($orders->pluck('billing_address'))
            ->filter()
            ->unique()
            ->values();

        if ($addressIds->isNotEmpty()) {
            $addressPhoneMap = \Illuminate\Support\Facades\DB::table('shipping_addresses')
                ->whereIn('id', $addressIds)
                ->pluck('phone', 'id')
                ->toArray();
        }
    }

    $matchedOrders = $orders->filter(function ($order) use ($normalizedPhone, $addressPhoneMap) {
        foreach (self::extractPhonesForTrustScore($order, $addressPhoneMap) as $candidatePhone) {
            if (self::normalizeTrustScorePhone($candidatePhone) === $normalizedPhone) {
                return true;
            }
        }

        return false;
    });

    $resolvedOrders = $matchedOrders->count();
    $deliveredOrders = $matchedOrders->where('order_status', 'delivered')->count();

    $score = $resolvedOrders > 0 ? round(($deliveredOrders / $resolvedOrders) * 100, 2) : 0;
    $displayScore = floor($score) == $score ? (int)$score : $score;

    return [
        'phone' => $phone,
        'score' => $displayScore,
        'delivered' => $deliveredOrders,
        'resolved_orders' => $resolvedOrders,
        'has_history' => $resolvedOrders > 0,
        'label' => $resolvedOrders > 0
            ? "هذا الزبون لديه نسبة استلام {$displayScore}% عبر جميع متاجر المنصة (استلم {$deliveredOrders} من أصل {$resolvedOrders} طلبات)"
            : 'لا يوجد سجل  لهذا الزبون عبر جميع متاجر المنصة بعد',
    ];
}
}
