@extends('layouts.admin.app')

@section('title', $adRequest->title ?? translate('ad_request'))

@section('content')
    @php($displayStatus = $adRequest->display_status)
    @php($receiptUrl = $adRequest->payment_receipt_full_url['path'] ?? null)
    @php($receiptExtension = strtolower(pathinfo($adRequest->payment_receipt ?? '', PATHINFO_EXTENSION)))

    <div class="content container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <div>
                <h2 class="h1 mb-0 text-capitalize d-flex align-items-center gap-2">
                    <img width="24" src="{{ dynamicAsset(path: 'public/assets/back-end/img/add-new-seller.png') }}" alt="">
                    {{ translate('ad_request') }} #{{ $adRequest->id }}
                </h2>
                <p class="mb-0 text-muted">{{ $adRequest->title }}</p>
            </div>

            <a href="{{ route('admin.ad-requests.index') }}" class="btn btn-outline-secondary">
                {{ translate('back') }}
            </a>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex flex-wrap gap-2 mb-4">
                            <span class="badge badge-soft-{{ $displayStatus === 'approved' ? 'info' : ($displayStatus === 'active' ? 'success' : ($displayStatus === 'rejected' ? 'danger' : ($displayStatus === 'expired' ? 'dark' : 'warning'))) }}">
                                {{ translate($displayStatus === 'pending' ? 'pending_approval' : $displayStatus) }}
                            </span>
                            <span class="badge badge-soft-{{ $adRequest->payment_status === 'uploaded' ? 'success' : 'warning' }}">
                                {{ translate('payment_status') }} : {{ translate($adRequest->payment_status ?? 'pending') }}
                            </span>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="pair-list">
                                    <div>
                                        <span class="key">{{ translate('vendor') }}</span>
                                        <span>:</span>
                                        <span class="value">{{ trim(($adRequest->vendor?->f_name ?? '') . ' ' . ($adRequest->vendor?->l_name ?? '')) ?: '-' }}</span>
                                    </div>
                                    <div>
                                        <span class="key">{{ translate('shop_name') }}</span>
                                        <span>:</span>
                                        <span class="value">{{ $adRequest->shop?->name ?? '-' }}</span>
                                    </div>
                                    <div>
                                        <span class="key">{{ translate('product') }}</span>
                                        <span>:</span>
                                        <span class="value">{{ $adRequest->product?->name ?? '-' }}</span>
                                    </div>
                                    <div>
                                        <span class="key">{{ translate('ad_pricing_plan') }}</span>
                                        <span>:</span>
                                        <span class="value">{{ $adRequest->plan_name ?? '-' }}</span>
                                    </div>
                                    <div>
                                        <span class="key">{{ translate('ad_placement') }}</span>
                                        <span>:</span>
                                        <span class="value">{{ translate($adRequest->placement ?? 'featured_products') }}</span>
                                    </div>
                                    <div>
                                        <span class="key">{{ translate('ad_duration') }}</span>
                                        <span>:</span>
                                        <span class="value">{{ $adRequest->duration_days }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="pair-list">
                                    <div>
                                        <span class="key">{{ translate('ad_price') }}</span>
                                        <span>:</span>
                                        <span class="value">{{ number_format((float) $adRequest->price, 2) }}</span>
                                    </div>
                                    <div>
                                        <span class="key">{{ translate('ad_plan_price') }}</span>
                                        <span>:</span>
                                        <span class="value">{{ number_format((float) ($adRequest->plan_price ?? 0), 2) }}</span>
                                    </div>
                                    <div>
                                        <span class="key">{{ translate('ad_plan_duration') }}</span>
                                        <span>:</span>
                                        <span class="value">{{ $adRequest->plan_duration_days ?? '-' }}</span>
                                    </div>
                                    <div>
                                        <span class="key">{{ translate('ad_start_date') }}</span>
                                        <span>:</span>
                                        <span class="value">{{ optional($adRequest->start_date)->format('Y-m-d H:i') ?? '-' }}</span>
                                    </div>
                                    <div>
                                        <span class="key">{{ translate('ad_end_date') }}</span>
                                        <span>:</span>
                                        <span class="value">{{ optional($adRequest->end_date)->format('Y-m-d H:i') ?? '-' }}</span>
                                    </div>
                                    <div>
                                        <span class="key">{{ translate('priority') }}</span>
                                        <span>:</span>
                                        <span class="value">{{ $adRequest->priority ?? 0 }}</span>
                                    </div>
                                    <div>
                                        <span class="key">{{ translate('created_at') }}</span>
                                        <span>:</span>
                                        <span class="value">{{ $adRequest->created_at?->format('Y-m-d H:i') }}</span>
                                    </div>
                                </div>
                            </div>

                            @if($adRequest->description)
                                <div class="col-12">
                                    <h5>{{ translate('ad_description') }}</h5>
                                    <p class="mb-0">{{ $adRequest->description }}</p>
                                </div>
                            @endif

                            @if($adRequest->notes)
                                <div class="col-12">
                                    <h5>{{ translate('note') }}</h5>
                                    <p class="mb-0">{{ $adRequest->notes }}</p>
                                </div>
                            @endif

                            @if($adRequest->rejection_reason)
                                <div class="col-12">
                                    <div class="alert alert-soft-danger mb-0">
                                        <strong>{{ translate('rejection_reason') }}:</strong> {{ $adRequest->rejection_reason }}
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card mb-3">
                    <div class="card-header">
                        <h4 class="mb-0">{{ translate('ad_preview') }}</h4>
                    </div>
                    <div class="card-body">
                        @if($adRequest->image_full_url['path'] ?? $adRequest->image_url)
                            <img
                                src="{{ $adRequest->image_full_url['path'] ?? $adRequest->image_url }}"
                                alt="{{ $adRequest->title }}"
                                class="img-fluid rounded border"
                            >
                        @else
                            <div class="text-muted">{{ translate('no_data_found') }}</div>
                        @endif
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">{{ translate('payment_receipt') }}</h4>
                    </div>
                    <div class="card-body">
                        @if($receiptUrl)
                            @if(in_array($receiptExtension, ['jpg', 'jpeg', 'png', 'webp']))
                                <img
                                    src="{{ $receiptUrl }}"
                                    alt="{{ translate('payment_receipt') }}"
                                    class="img-fluid rounded border mb-3"
                                >
                            @endif

                            <a href="{{ $receiptUrl }}" target="_blank" class="btn btn-outline--primary w-100">
                                <i class="tio-download"></i>
                                {{ translate('payment_receipt') }}
                            </a>
                        @else
                            <div class="text-muted">{{ translate('no_data_found') }}</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <h4 class="mb-0">{{ translate('ad_statistics') }}</h4>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">{{ translate('impressions_web') }}</div>
                            <div class="h3 mb-0">{{ number_format($adRequest->impressions_web ?? 0) }}</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">{{ translate('impressions_app') }}</div>
                            <div class="h3 mb-0">{{ number_format($adRequest->impressions_app ?? 0) }}</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">{{ translate('total_impressions') }}</div>
                            <div class="h3 mb-0">{{ number_format($adRequest->total_impressions) }}</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">{{ translate('clicks_web') }}</div>
                            <div class="h3 mb-0">{{ number_format($adRequest->clicks_web ?? 0) }}</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">{{ translate('clicks_app') }}</div>
                            <div class="h3 mb-0">{{ number_format($adRequest->clicks_app ?? 0) }}</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">{{ translate('total_clicks') }}</div>
                            <div class="h3 mb-0">{{ number_format($adRequest->total_clicks) }}</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">{{ translate('ctr') }}</div>
                            <div class="h3 mb-0">{{ number_format($adRequest->ctr, 2) }}%</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">{{ translate('completed_purchases_from_ad') }}</div>
                            <div class="h3 mb-0">{{ number_format($adRequest->completed_purchases_count ?? 0) }}</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">{{ translate('sales_from_ad') }}</div>
                            <div class="h3 mb-0">{{ number_format((float) ($adRequest->completed_purchases_amount ?? 0), 2) }} DZD</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">{{ translate('last_purchase_at') }}</div>
                            <div class="h5 mb-0">{{ optional($adRequest->last_purchase_at)->format('Y-m-d H:i') ?? '-' }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <h4 class="mb-0">{{ translate('ad_schedule') }}</h4>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.ad-requests.update', $adRequest->id) }}" method="post">
                    @csrf
                    @method('PUT')

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('ad_placement') }}</label>
                            <select name="placement" class="form-control">
                                @foreach($placements as $placementKey => $placementConfig)
                                    <option value="{{ $placementKey }}" {{ ($adRequest->placement ?? 'featured_products') === $placementKey ? 'selected' : '' }}>
                                        {{ translate($placementKey) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('ad_price') }}</label>
                            <input type="number" step="0.01" min="0" name="price" class="form-control" value="{{ old('price', $adRequest->price) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('ad_duration') }}</label>
                            <input type="number" min="1" name="duration_days" class="form-control" value="{{ old('duration_days', $adRequest->duration_days) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('ad_start_date') }}</label>
                            <input type="datetime-local" name="start_date" class="form-control" value="{{ old('start_date', optional($adRequest->start_date)->format('Y-m-d\\TH:i')) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('ad_end_date') }}</label>
                            <input type="datetime-local" name="end_date" class="form-control" value="{{ old('end_date', optional($adRequest->end_date)->format('Y-m-d\\TH:i')) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('priority') }}</label>
                            <input type="number" min="0" name="priority" class="form-control" value="{{ old('priority', $adRequest->priority ?? 0) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ translate('redirect_type') }}</label>
                            <select name="redirect_type" class="form-control">
                                <option value="">{{ translate('optional') }}</option>
                                @foreach(['product', 'shop', 'url'] as $redirectType)
                                    <option value="{{ $redirectType }}" {{ old('redirect_type', $adRequest->redirect_type ?? '') === $redirectType ? 'selected' : '' }}>
                                        {{ translate($redirectType) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ translate('redirect_url') }}</label>
                            <input type="url" name="redirect_url" class="form-control" value="{{ old('redirect_url', $adRequest->redirect_url ?? '') }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label">{{ translate('admin_note') }}</label>
                            <textarea name="admin_note" rows="4" class="form-control">{{ old('admin_note', $adRequest->admin_note ?? '') }}</textarea>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mt-4">
                        <button type="submit" class="btn btn--primary">{{ translate('save') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2 justify-content-between align-items-start">
                    <div class="d-flex flex-wrap gap-2">
                        @if(!in_array($displayStatus, ['active', 'approved']))
                            <form action="{{ route('admin.ad-requests.approve', $adRequest->id) }}" method="post">
                                @csrf
                                <button type="submit" class="btn btn-success">
                                    {{ translate('approve_ad') }}
                                </button>
                            </form>
                        @endif

                        <form action="{{ route('admin.ad-requests.destroy', $adRequest->id) }}" method="post" onsubmit="return confirm('{{ translate('want_to_delete_this_item') }}')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger">
                                {{ translate('delete') }}
                            </button>
                        </form>
                    </div>

                    <form action="{{ route('admin.ad-requests.reject', $adRequest->id) }}" method="post" class="w-100 mt-3">
                        @csrf
                        <label class="form-label">{{ translate('rejection_reason') }}</label>
                        <div class="d-flex flex-column gap-2">
                            <textarea name="rejection_reason" rows="3" class="form-control" required>{{ old('rejection_reason', $adRequest->rejection_reason ?? '') }}</textarea>
                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn btn-danger">
                                    {{ translate('reject_ad') }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
