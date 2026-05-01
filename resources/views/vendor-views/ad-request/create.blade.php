@extends('layouts.vendor.app')

@php($isEdit = isset($adRequest) && $adRequest)
@php($errors = $errors ?? new \Illuminate\Support\ViewErrorBag())
@php($receiptRequired = (int) ($paymentSettings['ad_receipt_required'] ?? 0) === 1)
@php($hasPricingPlans = isset($pricingPlans) && $pricingPlans->count() > 0)
@php($selectedPlanId = (string) old('ad_pricing_plan_id', $adRequest->ad_pricing_plan_id ?? ($pricingPlans->first()->id ?? '')))
@php($selectedPlan = $pricingPlans->firstWhere('id', (int) $selectedPlanId) ?? $pricingPlans->first())

@section('title', $isEdit ? translate('edit_ad_request') : translate('new_ad_request'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <div>
                <h2 class="h1 mb-0 text-capitalize d-flex align-items-center gap-2">
                    <img width="24" src="{{ dynamicAsset(path: 'public/assets/back-end/img/add-new-seller.png') }}" alt="">
                    {{ $isEdit ? translate('edit_ad_request') : translate('new_ad_request') }}
                </h2>
                <p class="mb-0 text-muted">
                    {{ translate('vendor_ads') }}
                </p>
            </div>

            <a href="{{ route('vendor.ad-request.index') }}" class="btn btn-outline--primary">
                <i class="tio-view-list"></i>
                {{ translate('ad_requests') }}
            </a>
        </div>

        @if($isEdit)
            <div class="card mb-3">
                <div class="card-body d-flex flex-wrap align-items-center gap-3">
                    <span class="badge badge-soft-{{ $adRequest->display_status === 'approved' ? 'info' : ($adRequest->display_status === 'active' ? 'success' : ($adRequest->display_status === 'rejected' ? 'danger' : ($adRequest->display_status === 'expired' ? 'dark' : 'warning'))) }}">
                        {{ translate($adRequest->display_status === 'pending' ? 'pending_approval' : $adRequest->display_status) }}
                    </span>

                    <span class="badge badge-soft-{{ $adRequest->payment_status === 'uploaded' ? 'success' : 'warning' }}">
                        {{ translate('payment_status') }} : {{ translate($adRequest->payment_status ?? 'pending') }}
                    </span>

                    @if($adRequest->rejection_reason)
                        <div class="text-danger">
                            <strong>{{ translate('rejection_reason') }}:</strong> {{ $adRequest->rejection_reason }}
                        </div>
                    @endif
                </div>
            </div>
        @endif

        <form
            action="{{ $isEdit ? route('vendor.ad-request.update', $adRequest->id) : route('vendor.ad-request.store') }}"
            method="post"
            enctype="multipart/form-data"
        >
            @csrf

            <div class="row g-3">
                <div class="col-lg-8">
                    @unless($hasPricingPlans)
                        <div class="alert alert-warning mb-3">
                            {{ translate('no_active_ad_plans') }}
                        </div>
                    @endunless

                    <div class="card">
                        <div class="card-header">
                            <h4 class="mb-0">{{ translate('ad_request') }}</h4>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">{{ translate('ad_title') }}</label>
                                    <input
                                        type="text"
                                        name="title"
                                        class="form-control @error('title') is-invalid @enderror"
                                        value="{{ old('title', $adRequest->title ?? '') }}"
                                        maxlength="255"
                                        required
                                    >
                                    @error('title')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">{{ translate('product') }}</label>
                                    <select name="product_id" class="form-control @error('product_id') is-invalid @enderror">
                                        <option value="">{{ translate('optional') }}</option>
                                        @foreach($products as $product)
                                            <option
                                                value="{{ $product->id }}"
                                                {{ (string) old('product_id', $adRequest->product_id ?? '') === (string) $product->id ? 'selected' : '' }}
                                            >
                                                {{ $product->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('product_id')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-12">
                                    <label class="form-label">{{ translate('select_ad_plan') }}</label>
                                    <select name="ad_pricing_plan_id" id="ad_pricing_plan_id" class="form-control @error('ad_pricing_plan_id') is-invalid @enderror" required {{ $hasPricingPlans ? '' : 'disabled' }}>
                                        @if(!$hasPricingPlans)
                                            <option value="">{{ translate('no_active_ad_plans') }}</option>
                                        @endif
                                        @foreach($pricingPlans as $plan)
                                            <option
                                                value="{{ $plan->id }}"
                                                data-name="{{ $plan->name }}"
                                                data-placement="{{ translate($plan->placement) }}"
                                                data-placement-key="{{ $plan->placement }}"
                                                data-price="{{ number_format((float) $plan->price, 2) }}"
                                                data-duration="{{ $plan->duration_days }}"
                                                data-currency="{{ $plan->currency }}"
                                                data-description="{{ $plan->description }}"
                                                {{ $selectedPlanId === (string) $plan->id ? 'selected' : '' }}
                                            >
                                                {{ $plan->name }} - {{ translate($plan->placement) }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('ad_pricing_plan_id')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-12">
                                    <div class="border rounded p-3 bg--light" id="ad-plan-preview">
                                        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                                            <div>
                                                <div class="text-muted small">{{ translate('selected_ad_plan') }}</div>
                                                <div class="font-weight-bold" data-plan-name>{{ $selectedPlan->name ?? '-' }}</div>
                                                <div class="small text-muted mt-1" data-plan-description>{{ $selectedPlan->description ?? '-' }}</div>
                                            </div>
                                            <div class="text-right">
                                                <div class="small text-muted">{{ translate('ad_plan_details') }}</div>
                                                <div>{{ translate('ad_placement') }}: <span data-plan-placement>{{ $selectedPlan ? translate($selectedPlan->placement) : '-' }}</span></div>
                                                <div>{{ translate('ad_plan_price') }}: <span data-plan-price>{{ $selectedPlan ? number_format((float) $selectedPlan->price, 2) : '0.00' }}</span> <span data-plan-currency>{{ $selectedPlan->currency ?? ($paymentSettings['ad_currency'] ?? 'DZD') }}</span></div>
                                                <div>{{ translate('ad_plan_duration') }}: <span data-plan-duration>{{ $selectedPlan->duration_days ?? 0 }}</span></div>
                                            </div>
                                        </div>
                                        <div class="alert alert-soft-info mt-3 mb-0">
                                            {{ translate('price_is_set_by_admin') }}. {{ translate('duration_is_set_by_admin') }}.
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">{{ translate('redirect_type') }}</label>
                                    <select name="redirect_type" class="form-control @error('redirect_type') is-invalid @enderror">
                                        <option value="">{{ translate('optional') }}</option>
                                        @foreach(['product', 'shop', 'url'] as $redirectType)
                                            <option
                                                value="{{ $redirectType }}"
                                                {{ old('redirect_type', $adRequest->redirect_type ?? '') === $redirectType ? 'selected' : '' }}
                                            >
                                                {{ translate($redirectType) }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('redirect_type')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">{{ translate('redirect_url') }}</label>
                                    <input
                                        type="url"
                                        name="redirect_url"
                                        class="form-control @error('redirect_url') is-invalid @enderror"
                                        value="{{ old('redirect_url', $adRequest->redirect_url ?? '') }}"
                                        placeholder="https://example.com"
                                    >
                                    @error('redirect_url')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-12">
                                    <label class="form-label">{{ translate('ad_description') }}</label>
                                    <textarea
                                        name="description"
                                        rows="4"
                                        class="form-control @error('description') is-invalid @enderror"
                                    >{{ old('description', $adRequest->description ?? '') }}</textarea>
                                    @error('description')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-12">
                                    <label class="form-label">{{ translate('note') }}</label>
                                    <textarea
                                        name="notes"
                                        rows="3"
                                        class="form-control @error('notes') is-invalid @enderror"
                                    >{{ old('notes', $adRequest->notes ?? '') }}</textarea>
                                    @error('notes')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">
                                        {{ translate('ad_image') }}
                                        @unless($isEdit)
                                            <span class="text-danger">*</span>
                                        @endunless
                                    </label>
                                    <input
                                        type="file"
                                        name="ad_image"
                                        accept=".jpg,.jpeg,.png,.webp"
                                        class="form-control @error('ad_image') is-invalid @enderror"
                                        {{ $isEdit ? '' : 'required' }}
                                    >
                                    <small class="text-muted">
                                        {{ translate('Format') }}: JPG, JPEG, PNG, WEBP | {{ translate('maximum_size') }}: 5MB
                                    </small>
                                    @error('ad_image')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror

                                    @if($isEdit && ($adRequest->image_full_url['path'] ?? $adRequest->image_url))
                                        <div class="mt-3">
                                            <img
                                                src="{{ $adRequest->image_full_url['path'] ?? $adRequest->image_url }}"
                                                alt="{{ $adRequest->title }}"
                                                class="img-fluid rounded border"
                                                style="max-height: 220px;"
                                            >
                                        </div>
                                    @endif
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">
                                        {{ translate('upload_payment_receipt') }}
                                        @if($receiptRequired && !$isEdit)
                                            <span class="text-danger">*</span>
                                        @endif
                                    </label>
                                    <input
                                        type="file"
                                        name="payment_receipt"
                                        accept=".jpg,.jpeg,.png,.webp,.pdf"
                                        class="form-control @error('payment_receipt') is-invalid @enderror"
                                    >
                                    <small class="text-muted">
                                        JPG, JPEG, PNG, WEBP, PDF | {{ translate('maximum_size') }}: 5MB
                                    </small>
                                    @error('payment_receipt')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror

                                    @if($isEdit && ($adRequest->payment_receipt_full_url['path'] ?? null))
                                        @php($receiptUrl = $adRequest->payment_receipt_full_url['path'])
                                        @php($receiptExtension = strtolower(pathinfo($adRequest->payment_receipt ?? '', PATHINFO_EXTENSION)))
                                        <div class="mt-3">
                                            @if(in_array($receiptExtension, ['jpg', 'jpeg', 'png', 'webp']))
                                                <img
                                                    src="{{ $receiptUrl }}"
                                                    alt="{{ translate('payment_receipt') }}"
                                                    class="img-fluid rounded border"
                                                    style="max-height: 220px;"
                                                >
                                            @else
                                                <a href="{{ $receiptUrl }}" target="_blank" class="btn btn-outline--primary">
                                                    <i class="tio-download"></i>
                                                    {{ translate('payment_receipt') }}
                                                </a>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card mb-3">
                        <div class="card-header">
                            <h4 class="mb-0 d-flex align-items-center gap-2">
                                <i class="tio-info-outined"></i>
                                {{ translate('payment_information') }}
                            </h4>
                        </div>
                        <div class="card-body d-flex flex-column gap-3">
                            <div>
                                <div class="text-muted small">{{ translate('payment_method') }}</div>
                                <div class="font-weight-bold">
                                    {{ $paymentSettings['ad_payment_method_name'] ?: translate('not_configured') }}
                                </div>
                            </div>
                            <div>
                                <div class="text-muted small">{{ translate('payment_account_name') }}</div>
                                <div class="font-weight-bold">
                                    {{ $paymentSettings['ad_payment_account_name'] ?: translate('not_configured') }}
                                </div>
                            </div>
                            <div>
                                <div class="text-muted small">{{ translate('payment_account_number') }}</div>
                                <div class="font-weight-bold">
                                    {{ $paymentSettings['ad_payment_account_number'] ?: translate('not_configured') }}
                                </div>
                            </div>
                            <div>
                                <div class="text-muted small">{{ translate('payment_instructions') }}</div>
                                <div>{{ $paymentSettings['ad_payment_instructions'] ?: translate('not_configured') }}</div>
                            </div>
                            <div class="alert alert-soft-info mb-0">
                                {{ $receiptRequired ? translate('receipt_required') : translate('receipt_uploaded') }}
                            </div>
                        </div>
                    </div>

                    @if($isEdit && ($adRequest->start_date || $adRequest->end_date || $adRequest->admin_note))
                        <div class="card mb-3">
                            <div class="card-header">
                                <h4 class="mb-0">{{ translate('ad_schedule') }}</h4>
                            </div>
                            <div class="card-body d-flex flex-column gap-2">
                                <div>
                                    <div class="text-muted small">{{ translate('ad_start_date') }}</div>
                                    <div>{{ optional($adRequest->start_date)->format('Y-m-d H:i') ?? '-' }}</div>
                                </div>
                                <div>
                                    <div class="text-muted small">{{ translate('ad_end_date') }}</div>
                                    <div>{{ optional($adRequest->end_date)->format('Y-m-d H:i') ?? '-' }}</div>
                                </div>
                                @if($adRequest->admin_note)
                                    <div>
                                        <div class="text-muted small">{{ translate('note') }}</div>
                                        <div>{{ $adRequest->admin_note }}</div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                    <div class="d-flex justify-content-end gap-2">
                        @if($isEdit)
                            <a href="{{ route('vendor.ad-request.show', $adRequest->id) }}" class="btn btn-outline-secondary">
                                {{ translate('cancel') }}
                            </a>
                        @endif

                        <button type="submit" class="btn btn--primary" {{ $hasPricingPlans ? '' : 'disabled' }}>
                            {{ $isEdit ? translate('update') : translate('submit_ad_request') }}
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script>
        (() => {
            const planSelect = document.getElementById('ad_pricing_plan_id');
            if (!planSelect) {
                return;
            }

            const preview = {
                name: document.querySelector('[data-plan-name]'),
                description: document.querySelector('[data-plan-description]'),
                placement: document.querySelector('[data-plan-placement]'),
                price: document.querySelector('[data-plan-price]'),
                currency: document.querySelector('[data-plan-currency]'),
                duration: document.querySelector('[data-plan-duration]'),
            };

            const updatePreview = () => {
                const option = planSelect.options[planSelect.selectedIndex];
                if (!option) {
                    return;
                }

                preview.name.textContent = option.dataset.name || '-';
                preview.description.textContent = option.dataset.description || '-';
                preview.placement.textContent = option.dataset.placement || '-';
                preview.price.textContent = option.dataset.price || '0.00';
                preview.currency.textContent = option.dataset.currency || 'DZD';
                preview.duration.textContent = option.dataset.duration || '0';
            };

            planSelect.addEventListener('change', updatePreview);
            updatePreview();
        })();
    </script>
@endsection
