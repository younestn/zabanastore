@extends('layouts.admin.app')

@section('title', translate('ad_requests'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <div>
                <h2 class="h1 mb-0 text-capitalize d-flex align-items-center gap-2">
                    <img width="24" src="{{ dynamicAsset(path: 'public/assets/back-end/img/add-new-seller.png') }}" alt="">
                    {{ translate('ad_requests') }}
                </h2>
                <p class="mb-0 text-muted">{{ translate('vendor_ads') }}</p>
            </div>

            <a href="{{ route('admin.ad-requests.pricing.index') }}" class="btn btn--primary">
                <i class="tio-dollar-outlined"></i>
                {{ translate('ad_pricing_plans') }}
            </a>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h4 class="mb-0">{{ translate('ad_payment_settings') }}</h4>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.ad-requests.settings') }}" method="post">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('payment_method') }}</label>
                            <input
                                type="text"
                                name="ad_payment_method_name"
                                class="form-control"
                                value="{{ old('ad_payment_method_name', $paymentSettings['ad_payment_method_name'] ?? '') }}"
                            >
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('payment_account_name') }}</label>
                            <input
                                type="text"
                                name="ad_payment_account_name"
                                class="form-control"
                                value="{{ old('ad_payment_account_name', $paymentSettings['ad_payment_account_name'] ?? '') }}"
                            >
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('payment_account_number') }}</label>
                            <input
                                type="text"
                                name="ad_payment_account_number"
                                class="form-control"
                                value="{{ old('ad_payment_account_number', $paymentSettings['ad_payment_account_number'] ?? '') }}"
                            >
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('currency') }}</label>
                            <input
                                type="text"
                                name="ad_currency"
                                class="form-control"
                                value="{{ old('ad_currency', $paymentSettings['ad_currency'] ?? 'DZD') }}"
                            >
                        </div>
                        <div class="col-md-4">
                            <label class="form-label d-block">{{ translate('upload_payment_receipt') }}</label>
                            <label class="switcher">
                                <input
                                    type="checkbox"
                                    class="switcher_input"
                                    name="ad_receipt_required"
                                    value="1"
                                    {{ (int) old('ad_receipt_required', $paymentSettings['ad_receipt_required'] ?? 0) === 1 ? 'checked' : '' }}
                                >
                                <span class="switcher_control"></span>
                            </label>
                        </div>
                        <div class="col-12">
                            <label class="form-label">{{ translate('payment_instructions') }}</label>
                            <textarea name="ad_payment_instructions" rows="4" class="form-control">{{ old('ad_payment_instructions', $paymentSettings['ad_payment_instructions'] ?? '') }}</textarea>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mt-4">
                        <button type="submit" class="btn btn--primary">{{ translate('save') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h4 class="mb-0">{{ translate('ad_requests') }}</h4>

                <form action="{{ route('admin.ad-requests.index') }}" method="get" class="d-flex gap-2">
                    <select name="status" class="form-control">
                        @foreach($statuses as $status)
                            <option value="{{ $status }}" {{ $selectedStatus === $status ? 'selected' : '' }}>
                                {{ translate($status === 'all' ? 'all' : ($status === 'pending' ? 'pending_approval' : $status)) }}
                            </option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-outline--primary">{{ translate('filter') }}</button>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-borderless table-thead-bordered table-align-middle mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>{{ translate('SL') }}</th>
                                <th>{{ translate('ad_title') }}</th>
                                <th>{{ translate('vendor') }}</th>
                                <th>{{ translate('ad_pricing_plan') }}</th>
                                <th>{{ translate('ad_placement') }}</th>
                                <th>{{ translate('ad_price') }}</th>
                                <th>{{ translate('payment_status') }}</th>
                                <th>{{ translate('status') }}</th>
                                <th>{{ translate('total_impressions') }}</th>
                                <th>{{ translate('total_clicks') }}</th>
                                <th>{{ translate('completed_purchases_from_ad') }}</th>
                                <th>{{ translate('sales_from_ad') }}</th>
                                <th>{{ translate('ad_start_date') }}</th>
                                <th>{{ translate('ad_end_date') }}</th>
                                <th>{{ translate('created_at') }}</th>
                                <th class="text-center">{{ translate('action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($adRequests as $key => $adRequest)
                                @php($displayStatus = $adRequest->display_status)
                                <tr>
                                    <td>{{ $adRequests->firstItem() + $key }}</td>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <span class="font-weight-bold">{{ $adRequest->title ?? ('#' . $adRequest->id) }}</span>
                                            <small class="text-muted">{{ $adRequest->product?->name ?? '-' }}</small>
                                        </div>
                                    </td>
                                    <td>{{ trim(($adRequest->vendor?->f_name ?? '') . ' ' . ($adRequest->vendor?->l_name ?? '')) ?: '-' }}</td>
                                    <td>{{ $adRequest->plan_name ?? '-' }}</td>
                                    <td>{{ translate($adRequest->placement ?? 'featured_products') }}</td>
                                    <td>{{ number_format((float) $adRequest->price, 2) }}</td>
                                    <td>
                                        <span class="badge badge-soft-{{ $adRequest->payment_status === 'uploaded' ? 'success' : 'warning' }}">
                                            {{ translate($adRequest->payment_status ?? 'pending') }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-soft-{{ $displayStatus === 'approved' ? 'info' : ($displayStatus === 'active' ? 'success' : ($displayStatus === 'rejected' ? 'danger' : ($displayStatus === 'expired' ? 'dark' : 'warning'))) }}">
                                            {{ translate($displayStatus === 'pending' ? 'pending_approval' : $displayStatus) }}
                                        </span>
                                    </td>
                                    <td>{{ number_format($adRequest->total_impressions) }}</td>
                                    <td>{{ number_format($adRequest->total_clicks) }}</td>
                                    <td>{{ number_format($adRequest->completed_purchases_count ?? 0) }}</td>
                                    <td>{{ number_format((float) ($adRequest->completed_purchases_amount ?? 0), 2) }} DZD</td>
                                    <td>{{ optional($adRequest->start_date)->format('Y-m-d H:i') ?? '-' }}</td>
                                    <td>{{ optional($adRequest->end_date)->format('Y-m-d H:i') ?? '-' }}</td>
                                    <td>{{ $adRequest->created_at?->format('Y-m-d H:i') }}</td>
                                    <td>
                                        <div class="d-flex justify-content-center gap-2">
                                            <a
                                                href="{{ route('admin.ad-requests.show', $adRequest->id) }}"
                                                class="btn btn-outline--primary btn-sm"
                                                title="{{ translate('view_details') }}"
                                            >
                                                <i class="tio-visible"></i>
                                                <span class="ml-1">{{ translate('details') }}</span>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="16" class="text-center py-5">{{ translate('no_ad_requests_found') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if($adRequests->hasPages())
                <div class="card-footer">
                    <div class="d-flex justify-content-end">
                        {!! $adRequests->links() !!}
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
