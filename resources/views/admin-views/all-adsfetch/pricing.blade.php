@extends('layouts.admin.app')

@section('title', translate('ad_pricing_plans'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <div>
                <h2 class="h1 mb-0 text-capitalize d-flex align-items-center gap-2">
                    <img width="24" src="{{ dynamicAsset(path: 'public/assets/back-end/img/add-new-seller.png') }}" alt="">
                    {{ translate('ad_pricing_plans') }}
                </h2>
                <p class="mb-0 text-muted">{{ translate('price_is_set_by_admin') }}</p>
            </div>

            <a href="{{ route('admin.ad-requests.index') }}" class="btn btn-outline-secondary">
                {{ translate('ad_requests') }}
            </a>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h4 class="mb-0">{{ $editingPlan ? translate('edit_ad_pricing_plan') : translate('create_ad_pricing_plan') }}</h4>
            </div>
            <div class="card-body">
                <form action="{{ $editingPlan ? route('admin.ad-requests.pricing.update', $editingPlan->id) : route('admin.ad-requests.pricing.store') }}" method="post">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('ad_plan_name') }}</label>
                            <input type="text" name="name" class="form-control" value="{{ old('name', $editingPlan->name ?? '') }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('ad_placement') }}</label>
                            <select name="placement" class="form-control" required>
                                @foreach($placements as $placementKey => $placementConfig)
                                    <option value="{{ $placementKey }}" {{ old('placement', $editingPlan->placement ?? 'home_top') === $placementKey ? 'selected' : '' }}>
                                        {{ translate($placementKey) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('ad_plan_currency') }}</label>
                            <input type="text" name="currency" class="form-control" value="{{ old('currency', $editingPlan->currency ?? 'DZD') }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('ad_plan_price') }}</label>
                            <input type="number" step="0.01" min="0" name="price" class="form-control" value="{{ old('price', $editingPlan->price ?? 0) }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('ad_plan_duration') }}</label>
                            <input type="number" min="1" name="duration_days" class="form-control" value="{{ old('duration_days', $editingPlan->duration_days ?? 7) }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('priority') }}</label>
                            <input type="number" min="0" name="sort_order" class="form-control" value="{{ old('sort_order', $editingPlan->sort_order ?? 0) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label d-block">{{ translate('status') }}</label>
                            <label class="switcher">
                                <input type="checkbox" class="switcher_input" name="status" value="1" {{ (int) old('status', $editingPlan->status ?? 1) === 1 ? 'checked' : '' }}>
                                <span class="switcher_control"></span>
                            </label>
                        </div>
                        <div class="col-12">
                            <label class="form-label">{{ translate('ad_description') }}</label>
                            <textarea name="description" rows="3" class="form-control">{{ old('description', $editingPlan->description ?? '') }}</textarea>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4">
                        @if($editingPlan)
                            <a href="{{ route('admin.ad-requests.pricing.index') }}" class="btn btn-outline-secondary">{{ translate('cancel') }}</a>
                        @endif
                        <button type="submit" class="btn btn--primary">{{ $editingPlan ? translate('update') : translate('save') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">{{ translate('ad_pricing_plans') }}</h4>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-borderless table-thead-bordered table-align-middle mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>{{ translate('SL') }}</th>
                                <th>{{ translate('ad_plan_name') }}</th>
                                <th>{{ translate('ad_placement') }}</th>
                                <th>{{ translate('ad_plan_price') }}</th>
                                <th>{{ translate('ad_plan_duration') }}</th>
                                <th>{{ translate('ad_plan_currency') }}</th>
                                <th>{{ translate('status') }}</th>
                                <th>{{ translate('action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($pricingPlans as $key => $plan)
                                <tr>
                                    <td>{{ $key + 1 }}</td>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <span class="font-weight-bold">{{ $plan->name }}</span>
                                            @if($plan->description)
                                                <small class="text-muted">{{ \Illuminate\Support\Str::limit($plan->description, 70) }}</small>
                                            @endif
                                        </div>
                                    </td>
                                    <td>{{ translate($plan->placement) }}</td>
                                    <td>{{ number_format((float) $plan->price, 2) }}</td>
                                    <td>{{ $plan->duration_days }}</td>
                                    <td>{{ $plan->currency }}</td>
                                    <td>
                                        <span class="badge badge-soft-{{ $plan->status ? 'success' : 'secondary' }}">
                                            {{ translate($plan->status ? 'enabled' : 'disabled') }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-2">
                                            <a href="{{ route('admin.ad-requests.pricing.edit', $plan->id) }}" class="btn btn-outline-info btn-sm">
                                                <i class="tio-edit"></i>
                                            </a>
                                            <form action="{{ route('admin.ad-requests.pricing.status', $plan->id) }}" method="post">
                                                @csrf
                                                <button type="submit" class="btn btn-outline--primary btn-sm">
                                                    {{ translate($plan->status ? 'disable' : 'enable') }}
                                                </button>
                                            </form>
                                            <form action="{{ route('admin.ad-requests.pricing.delete', $plan->id) }}" method="post" onsubmit="return confirm('{{ translate('want_to_delete_this_item') }}')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger btn-sm">
                                                    <i class="tio-delete"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-5">{{ translate('no_data_found') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
