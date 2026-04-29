@extends('layouts.admin.app')

@section('title', translate('verification_evaluation'))

@section('content')
    @php($metrics = $badgeEvaluation['metrics'])
    @php($currentBadge = $badgeEvaluation['current_badge'])
    @php($automaticBadge = $badgeEvaluation['automatic_badge'])
    @php($documents = $metrics['documents'] ?? [])

    <div class="content container-fluid">
        <div class="mb-4 d-flex justify-content-between align-items-center gap-3 flex-wrap">
            <h2 class="h1 mb-0 text-capitalize d-flex align-items-center gap-2">
                <img src="{{ dynamicAsset(path: 'public/assets/back-end/img/add-new-seller.png') }}" alt="">
                {{ translate('verification_evaluation') }}
            </h2>
            <a href="{{ route('admin.vendors.view', ['id' => $seller['id']]) }}" class="btn btn-outline-primary">
                <i class="fi fi-rr-angle-left"></i>
                {{ translate('back') }}
            </a>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-6 col-xl-3">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-muted text-capitalize mb-2">{{ translate('compliance_score') }}</div>
                        <h2 class="mb-0">{{ number_format($metrics['score'], 2) }}%</h2>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-muted text-capitalize mb-2">{{ translate('seller_badge') }}</div>
                        @include('partials._seller-badge', ['badge' => $currentBadge, 'showEmpty' => true])
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-muted text-capitalize mb-2">{{ translate('automatic_badge') }}</div>
                        @include('partials._seller-badge', ['badge' => $automaticBadge, 'showEmpty' => true])
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-muted text-capitalize mb-2">{{ translate('manual_override') }}</div>
                        <h4 class="mb-1">{{ $badgeEvaluation['manual_override'] ? translate('yes') : translate('no') }}</h4>
                        @if($badgeEvaluation['manual_override_reason'])
                            <div class="fs-12 text-muted">{{ $badgeEvaluation['manual_override_reason'] }}</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card h-100">
                    <div class="card-header">
                        <h4 class="mb-0">{{ translate('badge_eligibility_details') }}</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-borderless table-align-middle">
                                <tbody>
                                <tr>
                                    <th>{{ translate('total_orders') }}</th>
                                    <td>{{ $metrics['total_orders'] }}</td>
                                    <th>{{ translate('completed_orders') }}</th>
                                    <td>{{ $metrics['completed_orders'] }}</td>
                                </tr>
                                <tr>
                                    <th>{{ translate('cancelled_orders') }}</th>
                                    <td>{{ $metrics['cancelled_orders'] }}</td>
                                    <th>{{ translate('cancellation_rate') }}</th>
                                    <td>{{ number_format($metrics['cancellation_rate'], 2) }}%</td>
                                </tr>
                                <tr>
                                    <th>{{ translate('delayed_orders') }}</th>
                                    <td>{{ $metrics['delayed_orders'] }}</td>
                                    <th>{{ translate('delay_rate') }}</th>
                                    <td>{{ number_format($metrics['delay_rate'], 2) }}%</td>
                                </tr>
                                <tr>
                                    <th>{{ translate('average_rating') }}</th>
                                    <td>{{ number_format($metrics['average_rating'], 2) }}</td>
                                    <th>{{ translate('seller_joined_at') }}</th>
                                    <td>{{ $metrics['seller_joined_at'] ? $metrics['seller_joined_at']->format('Y-m-d') : '-' }}</td>
                                </tr>
                                <tr>
                                    <th>{{ translate('total_sales') }}</th>
                                    <td>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $metrics['total_sales'])) }}</td>
                                    <th>{{ translate('net_profit') }}</th>
                                    <td>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $metrics['net_profit'])) }}</td>
                                </tr>
                                <tr>
                                    <th>{{ translate('product_count') }}</th>
                                    <td>{{ $metrics['product_count'] }}</td>
                                    <th>{{ translate('published_products') }}</th>
                                    <td>{{ $metrics['published_products'] }}</td>
                                </tr>
                                <tr>
                                    <th>{{ translate('uploaded_documents') }}</th>
                                    <td colspan="3">
                                        <div>{{ translate('document_status') }}: {{ translate($documents['status'] ?? 'missing') }}</div>
                                        <div class="fs-12 text-muted">
                                            {{ translate('tin_number') }}: {{ $documents['tax_identification_number'] ?? '-' }}
                                            |
                                            {{ translate('expire_date') }}: {{ $documents['tin_expire_date'] ?? '-' }}
                                        </div>
                                    </td>
                                </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex flex-column gap-2 mt-3">
                            @foreach($badgeEvaluation['eligibility_details'] as $detail)
                                <div class="d-flex justify-content-between gap-3 border rounded px-3 py-2">
                                    <div>
                                        <strong>{{ $detail['badge_name'] }}</strong>
                                        <div class="fs-12 text-muted">{{ $detail['reason'] }}</div>
                                    </div>
                                    <span class="badge {{ $detail['eligible'] ? 'badge-success text-bg-success' : 'badge-danger text-bg-danger' }}">
                                        {{ $detail['eligible'] ? translate('eligible_for_badge') : translate('not_eligible_for_badge') }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card mb-3">
                    <div class="card-header">
                        <h4 class="mb-0">{{ translate('manual_badge') }}</h4>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('admin.vendors.badge-update', ['id' => $seller['id']]) }}" method="post">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label" for="badge_key">{{ translate('select_badge') }}</label>
                                <select class="form-control" id="badge_key" name="badge_key" required>
                                    @foreach($badgeEvaluation['badge_options'] as $key => $badge)
                                        <option value="{{ $key }}" {{ ($currentBadge['key'] ?? null) === $key ? 'selected' : '' }}>
                                            {{ $badge['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="manual_override_reason">{{ translate('override_reason') }}</label>
                                <textarea class="form-control" id="manual_override_reason" name="manual_override_reason" rows="4" required>{{ old('manual_override_reason', $badgeEvaluation['manual_override_reason']) }}</textarea>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                {{ translate('save_badge') }}
                            </button>
                        </form>

                        @if($badgeEvaluation['manual_override'])
                            <form action="{{ route('admin.vendors.badge-restore-automatic', ['id' => $seller['id']]) }}" method="post" class="mt-3">
                                @csrf
                                <button type="submit" class="btn btn-outline-info w-100">
                                    {{ translate('restore_automatic_badge') }}
                                </button>
                            </form>
                        @endif
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">{{ translate('badge_history') }}</h4>
                    </div>
                    <div class="card-body">
                        @forelse($badgeEvaluation['histories'] as $history)
                            <div class="border-bottom pb-2 mb-2">
                                <div class="fw-semibold">{{ $history->old_badge_key ?? '-' }} -> {{ $history->new_badge_key ?? '-' }}</div>
                                <div class="fs-12 text-muted">
                                    {{ translate($history->change_type) }}
                                    |
                                    {{ optional($history->created_at)->format('Y-m-d H:i') }}
                                </div>
                                @if($history->reason)
                                    <div class="fs-12">{{ translate($history->reason) }}</div>
                                @endif
                            </div>
                        @empty
                            <div class="text-muted">{{ translate('no_data_found') }}</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
