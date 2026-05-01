<?php

namespace App\Http\Controllers;

use App\Models\AdRequest;
use App\Models\Product;
use App\Services\AdRequestService;
use Devrabiul\ToastMagic\Facades\ToastMagic;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class VendorAdRequestController extends Controller
{
    public function __construct(
        private readonly AdRequestService $adRequestService,
    ) {
    }

    public function index(Request $request): View
    {
        $seller = auth('seller')->user();
        $status = $request->string('status')->toString();

        $adRequests = AdRequest::query()
            ->with(['product', 'shop', 'pricingPlan'])
            ->where('vendor_id', $seller->id)
            ->when($status !== '' && $status !== 'all', function ($query) use ($status) {
                if ($status === 'active') {
                    $query->whereIn('status', ['approved', 'active'])
                        ->whereNotNull('start_date')
                        ->whereNotNull('end_date')
                        ->where('start_date', '<=', now())
                        ->where('end_date', '>=', now());
                    return;
                }

                if ($status === 'expired') {
                    $query->whereIn('status', ['approved', 'active', 'expired'])
                        ->whereNotNull('end_date')
                        ->where('end_date', '<', now());
                    return;
                }

                $query->where('status', $status);
            })
            ->latest()
            ->paginate((int) (getWebConfig(name: 'pagination_limit') ?? 15))
            ->appends($request->all());

        return view('vendor-views.ad-request.index', [
            'adRequests' => $adRequests,
            'statuses' => ['all', 'pending', 'approved', 'active', 'rejected', 'expired'],
            'selectedStatus' => $status !== '' ? $status : 'all',
        ]);
    }

    public function create(): View
    {
        $seller = auth('seller')->user();

        return view('vendor-views.ad-request.create', [
            'adRequest' => null,
            'products' => $this->getSellerProducts($seller->id),
            'pricingPlans' => $this->adRequestService->getVendorPricingPlans(),
            'paymentSettings' => $this->adRequestService->getPaymentSettings(),
        ]);
    }

    public function show(int $id): View
    {
        $seller = auth('seller')->user();
        $adRequest = AdRequest::query()
            ->with(['product', 'shop', 'approvedBy', 'rejectedBy', 'pricingPlan'])
            ->where('vendor_id', $seller->id)
            ->findOrFail($id);

        return view('vendor-views.ad-request.show', compact('adRequest'));
    }

    public function edit(int $id): View
    {
        $seller = auth('seller')->user();
        $adRequest = AdRequest::query()
            ->with(['product', 'shop', 'pricingPlan'])
            ->where('vendor_id', $seller->id)
            ->findOrFail($id);

        abort_unless($adRequest->isEditableByVendor(), 403);

        return view('vendor-views.ad-request.create', [
            'adRequest' => $adRequest,
            'products' => $this->getSellerProducts($seller->id),
            'pricingPlans' => $this->adRequestService->getVendorPricingPlans($adRequest->ad_pricing_plan_id),
            'paymentSettings' => $this->adRequestService->getPaymentSettings(),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $seller = auth('seller')->user();
        $validated = $this->validateRequest($request, $seller->id);
        $validated = $this->hydrateRedirectData($validated, $seller->shop?->id);

        $adRequest = $this->adRequestService->createVendorAdRequest(
            seller: $seller,
            validated: $validated,
            adImage: $request->file('ad_image'),
            receiptFile: $request->file('payment_receipt')
        );

        $message = translate('ad_request_submitted_successfully');

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'id' => $adRequest->id,
                    'status' => $adRequest->status,
                    'image_url' => $adRequest->image_full_url['path'] ?? $adRequest->image_url,
                    'redirect_url' => route('vendor.ad-request.show', $adRequest->id),
                ],
            ]);
        }

        ToastMagic::success($message);
        return redirect()->route('vendor.ad-request.show', $adRequest->id);
    }

    public function update(Request $request, int $id): RedirectResponse|JsonResponse
    {
        $seller = auth('seller')->user();
        $adRequest = AdRequest::query()
            ->where('vendor_id', $seller->id)
            ->findOrFail($id);

        $validated = $this->validateRequest($request, $seller->id, $adRequest);
        $validated = $this->hydrateRedirectData($validated, $seller->shop?->id);

        $updatedAdRequest = $this->adRequestService->updateVendorAdRequest(
            adRequest: $adRequest,
            validated: $validated,
            adImage: $request->file('ad_image'),
            receiptFile: $request->file('payment_receipt')
        );

        $message = translate('ad_request_updated_successfully');

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'id' => $updatedAdRequest->id,
                    'status' => $updatedAdRequest->status,
                    'redirect_url' => route('vendor.ad-request.show', $updatedAdRequest->id),
                ],
            ]);
        }

        ToastMagic::success($message);
        return redirect()->route('vendor.ad-request.show', $updatedAdRequest->id);
    }

    private function getSellerProducts(int $sellerId)
    {
        return Product::query()
            ->where('added_by', 'seller')
            ->where('user_id', $sellerId)
            ->where('request_status', 1)
            ->orderByDesc('id')
            ->get(['id', 'name', 'status']);
    }

    private function validateRequest(Request $request, int $sellerId, ?AdRequest $adRequest = null): array
    {
        $paymentSettings = $this->adRequestService->getPaymentSettings();
        $receiptIsRequired = (int) ($paymentSettings['ad_receipt_required'] ?? 0) === 1 && !$adRequest?->payment_receipt;

        $validator = Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'ad_pricing_plan_id' => [
                'required',
                Rule::exists('ad_pricing_plans', 'id')->where(function ($query) use ($adRequest) {
                    $query->where('status', true);

                    if ($adRequest?->ad_pricing_plan_id) {
                        $query->orWhere('id', $adRequest->ad_pricing_plan_id);
                    }
                }),
            ],
            'product_id' => [
                'nullable',
                'required_if:redirect_type,product',
                Rule::exists('products', 'id')->where(function ($query) use ($sellerId) {
                    $query->where('user_id', $sellerId)->where('added_by', 'seller');
                }),
            ],
            'ad_type' => ['nullable', Rule::in($this->adRequestService->getLegacyAdTypes())],
            'redirect_type' => ['nullable', Rule::in(['product', 'shop', 'url'])],
            'redirect_url' => ['nullable', 'url', 'required_if:redirect_type,url'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'ad_image' => [
                $adRequest ? 'nullable' : 'required',
                'file',
                'mimes:' . implode(',', config('ad_requests.upload.image_mimes', [])),
                'max:' . (int) config('ad_requests.upload.max_kb', 5120),
            ],
            'payment_receipt' => [
                $receiptIsRequired ? 'required' : 'nullable',
                'file',
                'mimes:' . implode(',', config('ad_requests.upload.receipt_mimes', [])),
                'max:' . (int) config('ad_requests.upload.max_kb', 5120),
            ],
        ], [
            'payment_receipt.required' => translate('upload_payment_receipt'),
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson() || $request->ajax()) {
                throw new ValidationException($validator);
            }

            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        return $validator->validated();
    }

    private function hydrateRedirectData(array $validated, ?int $shopId): array
    {
        if (($validated['redirect_type'] ?? null) === 'product' && !empty($validated['product_id'])) {
            $validated['redirect_id'] = (int) $validated['product_id'];
        }

        if (($validated['redirect_type'] ?? null) === 'shop' && $shopId) {
            $validated['redirect_id'] = $shopId;
        }

        return $validated;
    }
}
