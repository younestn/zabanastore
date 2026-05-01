<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdRequest;
use App\Models\AdPricingPlan;
use App\Models\Notification;
use App\Services\AdRequestService;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminAdRequestController extends Controller
{
    public function __construct(
        private readonly AdRequestService $adRequestService,
    ) {
    }

    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();

        $adRequests = AdRequest::query()
            ->with(['vendor.shop', 'product'])
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
                    $query->where(function ($expiredQuery) {
                        $expiredQuery->where('status', 'expired')
                            ->orWhere(function ($fallbackQuery) {
                                $fallbackQuery->whereIn('status', ['approved', 'active'])
                                    ->whereNotNull('end_date')
                                    ->where('end_date', '<', now());
                            });
                    });

                    return;
                }

                $query->where('status', $status);
            })
            ->latest()
            ->paginate((int) (getWebConfig(name: 'pagination_limit') ?? 15))
            ->appends($request->all());

        return view('admin-views.all-adsfetch.index', [
            'adRequests' => $adRequests,
            'paymentSettings' => $this->adRequestService->getPaymentSettings(),
            'placements' => $this->adRequestService->getPlacements(),
            'statuses' => ['all', 'pending', 'approved', 'active', 'rejected', 'expired'],
            'selectedStatus' => $status !== '' ? $status : 'all',
        ]);
    }

    public function pricingIndex(?int $id = null): View
    {
        $pricingPlans = $this->adRequestService->getPricingPlans();
        $editingPlan = $id ? AdPricingPlan::query()->findOrFail($id) : null;

        return view('admin-views.all-adsfetch.pricing', [
            'pricingPlans' => $pricingPlans,
            'editingPlan' => $editingPlan,
            'placements' => $this->adRequestService->getPlacements(),
        ]);
    }

    public function show(int $id): View
    {
        $adRequest = AdRequest::query()
            ->with(['vendor.shop', 'product', 'approvedBy', 'rejectedBy', 'pricingPlan'])
            ->findOrFail($id);

        return view('admin-views.all-adsfetch.show', [
            'adRequest' => $adRequest,
            'paymentSettings' => $this->adRequestService->getPaymentSettings(),
            'placements' => $this->adRequestService->getPlacements(),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $adRequest = AdRequest::query()->findOrFail($id);
        $validated = $this->validateAdminMetadata($request);
        $this->adRequestService->updateAdminMetadata($adRequest, $validated, (int) auth('admin')->id());

        Toastr::success(translate('ad_request_updated_successfully'));
        return redirect()->back();
    }

    public function approve(Request $request, int $id): RedirectResponse
    {
        $adRequest = AdRequest::query()->findOrFail($id);
        $validated = $this->validateAdminMetadata($request, true);

        if (!empty($validated)) {
            $adRequest = $this->adRequestService->updateAdminMetadata(
                $adRequest,
                $validated,
                (int) auth('admin')->id()
            );
        }

        $approvedAdRequest = $this->adRequestService->approve($adRequest, (int) auth('admin')->id());
        $this->dispatchSellerNotification(
            $approvedAdRequest,
            translate('Ad Request Approved'),
            translate('ad_request_approved_successfully'),
            'success'
        );

        Toastr::success(translate('ad_request_approved_successfully'));
        return redirect()->back();
    }

    public function reject(Request $request, int $id): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'rejection_reason' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        $adRequest = AdRequest::query()->findOrFail($id);
        $rejectedAdRequest = $this->adRequestService->reject(
            $adRequest,
            (string) $request->input('rejection_reason'),
            (int) auth('admin')->id()
        );

        $this->dispatchSellerNotification(
            $rejectedAdRequest,
            translate('Ad Request Rejected'),
            (string) $request->input('rejection_reason'),
            'warning'
        );

        Toastr::success(translate('ad_request_rejected_successfully'));
        return redirect()->back();
    }

    public function destroy(int $id): RedirectResponse
    {
        $adRequest = AdRequest::query()->findOrFail($id);
        $adRequest->delete();

        Toastr::success(translate('successfully_deleted'));
        return redirect()->route('admin.ad-requests.index');
    }

    public function updatePaymentSettings(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'ad_payment_method_name' => ['nullable', 'string', 'max:255'],
            'ad_payment_account_name' => ['nullable', 'string', 'max:255'],
            'ad_payment_account_number' => ['nullable', 'string', 'max:255'],
            'ad_payment_instructions' => ['nullable', 'string'],
            'ad_default_price' => ['nullable', 'numeric', 'min:0'],
            'ad_currency' => ['nullable', 'string', 'max:20'],
            'ad_receipt_required' => ['nullable', 'in:0,1'],
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        $this->adRequestService->updatePaymentSettings($validator->validated());

        Toastr::success(translate('successfully_updated'));
        return redirect()->back();
    }

    public function storePricingPlan(Request $request): RedirectResponse
    {
        $validated = $this->validatePricingPlan($request);
        $this->adRequestService->createPricingPlan($validated);

        Toastr::success(translate('successfully_added'));
        return redirect()->route('admin.ad-requests.pricing.index');
    }

    public function updatePricingPlan(Request $request, int $id): RedirectResponse
    {
        $plan = AdPricingPlan::query()->findOrFail($id);
        $validated = $this->validatePricingPlan($request, $plan);
        $this->adRequestService->updatePricingPlan($plan, $validated);

        Toastr::success(translate('successfully_updated'));
        return redirect()->route('admin.ad-requests.pricing.index');
    }

    public function updatePricingPlanStatus(int $id): RedirectResponse
    {
        $plan = AdPricingPlan::query()->findOrFail($id);
        $this->adRequestService->togglePricingPlanStatus($plan);

        Toastr::success(translate('successfully_status_updated'));
        return redirect()->route('admin.ad-requests.pricing.index');
    }

    public function deletePricingPlan(int $id): RedirectResponse
    {
        $plan = AdPricingPlan::query()->findOrFail($id);
        $deleted = $this->adRequestService->deletePricingPlan($plan);

        Toastr::success($deleted ? translate('successfully_deleted') : translate('pricing_plan_disabled_because_it_is_already_used'));
        return redirect()->route('admin.ad-requests.pricing.index');
    }

    private function validateAdminMetadata(Request $request, bool $allowEmpty = false): array
    {
        $validator = Validator::make($request->all(), [
            'placement' => ['nullable', Rule::in($this->adRequestService->getPlacementKeys())],
            'price' => ['nullable', 'numeric', 'min:0'],
            'duration_days' => ['nullable', 'integer', 'min:1'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'priority' => ['nullable', 'integer', 'min:0'],
            'admin_note' => ['nullable', 'string'],
            'redirect_type' => ['nullable', Rule::in(['product', 'shop', 'url'])],
            'redirect_id' => ['nullable', 'integer'],
            'redirect_url' => ['nullable', 'url', 'required_if:redirect_type,url'],
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        $validated = array_filter(
            $validator->validated(),
            static fn ($value) => $value !== null && $value !== ''
        );

        if (!$allowEmpty && empty($validated)) {
            throw ValidationException::withMessages([
                'form' => translate('no_valid_fields_to_update'),
            ]);
        }

        return $validated;
    }

    private function validatePricingPlan(Request $request, ?AdPricingPlan $plan = null): array
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'placement' => ['required', Rule::in($this->adRequestService->getPlacementKeys())],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'duration_days' => ['required', 'integer', 'min:1'],
            'currency' => ['nullable', 'string', 'max:20'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'in:0,1'],
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        $validated = $validator->validated();

        if ($plan && !array_key_exists('status', $validated)) {
            $validated['status'] = $plan->status;
        }

        return $validated;
    }

    private function dispatchSellerNotification(AdRequest $adRequest, string $title, string $description, string $type): void
    {
        try {
            if (!Schema::hasTable('notifications')) {
                return;
            }

            $payload = [
                'sent_by' => 'admin',
                'sent_to' => 'seller',
                'title' => $title,
                'description' => $description,
                'notification_count' => 1,
                'status' => 1,
            ];

            if (Schema::hasColumn('notifications', 'type')) {
                $payload['type'] = $type;
            }

            if (Schema::hasColumn('notifications', 'ad_request_id')) {
                $payload['ad_request_id'] = $adRequest->id;
            }

            if (Schema::hasColumn('notifications', 'user_id')) {
                if (!Schema::hasTable('users')) {
                    return;
                }

                $userExists = DB::table('users')->where('id', $adRequest->vendor_id)->exists();
                if (!$userExists) {
                    return;
                }

                $payload['user_id'] = $adRequest->vendor_id;
            }

            Notification::query()->create($payload);
        } catch (\Throwable) {
        }
    }
}
