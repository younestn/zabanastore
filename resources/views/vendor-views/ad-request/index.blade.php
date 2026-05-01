@extends('layouts.vendor.app')

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

            <div class="d-flex gap-2">
                <a href="{{ route('vendor.vendor1.test') }}" class="btn btn--primary">
                    <i class="tio-add"></i>
                    {{ translate('new_ad_request') }}
                </a>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h4 class="mb-0">{{ translate('ad_requests') }}</h4>

                <form action="{{ route('vendor.ad-request.index') }}" method="get" class="d-flex gap-2">
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
                                <th>{{ translate('product') }}</th>
                                <th>{{ translate('ad_pricing_plan') }}</th>
                                <th>{{ translate('ad_placement') }}</th>
                                <th>{{ translate('ad_price') }}</th>
                                <th>{{ translate('payment_status') }}</th>
                                <th>{{ translate('status') }}</th>
                                <th>{{ translate('total_impressions') }}</th>
                                <th>{{ translate('total_clicks') }}</th>
                                <th>{{ translate('created_at') }}</th>
                                <th class="text-center">{{ translate('action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($adRequests as $key => $adRequest)
                                <tr>
                                    <td>{{ $adRequests->firstItem() + $key }}</td>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <span class="font-weight-bold">{{ $adRequest->title ?? ('#' . $adRequest->id) }}</span>
                                            @if($adRequest->rejection_reason)
                                                <small class="text-danger">{{ \Illuminate\Support\Str::limit($adRequest->rejection_reason, 80) }}</small>
                                            @endif
                                        </div>
                                    </td>
                                    <td>{{ $adRequest->product?->name ?? '-' }}</td>
                                    <td>{{ $adRequest->plan_name ?? '-' }}</td>
                                    <td>{{ translate($adRequest->placement ?? 'home_top') }}</td>
                                    <td>{{ number_format((float) $adRequest->price, 2) }}</td>
                                    <td>
                                        <span class="badge badge-soft-{{ $adRequest->payment_status === 'uploaded' ? 'success' : 'warning' }}">
                                            {{ translate($adRequest->payment_status ?? 'pending') }}
                                        </span>
                                    </td>
                                    <td>
                                        @php($displayStatus = $adRequest->display_status)
                                        <span class="badge badge-soft-{{ $displayStatus === 'approved' ? 'info' : ($displayStatus === 'active' ? 'success' : ($displayStatus === 'rejected' ? 'danger' : ($displayStatus === 'expired' ? 'dark' : 'warning'))) }}">
                                            {{ translate($displayStatus === 'pending' ? 'pending_approval' : $displayStatus) }}
                                        </span>
                                    </td>
                                    <td>{{ number_format($adRequest->total_impressions) }}</td>
                                    <td>{{ number_format($adRequest->total_clicks) }}</td>
                                    <td>{{ $adRequest->created_at?->format('Y-m-d H:i') }}</td>
                                    <td>
                                        <div class="d-flex justify-content-center gap-2">
                                            <a
                                                href="{{ route('vendor.ad-request.show', $adRequest->id) }}"
                                                class="btn btn-outline--primary btn-sm square-btn"
                                                title="{{ translate('view') }}"
                                            >
                                                <i class="tio-visible"></i>
                                            </a>

                                            @if($adRequest->isEditableByVendor())
                                                <a
                                                    href="{{ route('vendor.ad-request.edit', $adRequest->id) }}"
                                                    class="btn btn-outline-info btn-sm square-btn"
                                                    title="{{ translate('edit') }}"
                                                >
                                                    <i class="tio-edit"></i>
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="12" class="text-center py-5">{{ translate('no_ad_requests_found') }}</td>
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
