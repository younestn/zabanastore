@extends('layouts.admin.app')

@section('title', 'إدارة عمولات البائعين')

@section('content')
    <div class="content container-fluid">
        <div class="mb-3">
            <h2 class="h1 mb-0 d-flex align-items-center gap-2">
                <img width="20" src="{{ dynamicAsset(path: 'public/assets/back-end/img/earning_report.png') }}" alt="">
                إدارة عمولات البائعين
            </h2>
        </div>

        @if(isset($activeThresholdAlerts) && $activeThresholdAlerts->count() > 0)
            <div class="card mb-4 border-warning">
                <div class="card-header bg-warning bg-opacity-10">
                    <h4 class="mb-0">تنبيهات تجاوز الحد</h4>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        @foreach($activeThresholdAlerts as $alert)
                            <div class="col-lg-4 col-md-6">
                                <div class="border rounded p-3 h-100">
                                    <h5 class="mb-2">{{ $alert->seller?->f_name }} {{ $alert->seller?->l_name }}</h5>
                                    <div class="text-muted mb-2">{{ $alert->seller?->shop?->name }}</div>
                                    <div><strong>الحد:</strong> {{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $alert->threshold_amount)) }}</div>
                                    <div><strong>غير المسدد:</strong> {{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $alert->unpaid_amount)) }}</div>
                                    <a href="{{ route('admin.vendors.commission-invoices.show', $alert->seller_id) }}"
                                       class="btn btn-outline-warning btn-sm mt-3">
                                        عرض التفاصيل
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        <div class="card mb-3">
            <div class="card-body">
                <form action="{{ route('admin.vendors.commission-invoices.index') }}" method="GET">
                    <h4 class="mb-3">تصفية البيانات</h4>
                    <div class="row gy-3 gx-2 align-items-end">
                        <div class="col-sm-6 col-md-3">
                            <label class="mb-2">الفترة</label>
                            <select class="form-select" name="date_type" id="date_type">
                                <option value="this_year" {{ $dateType == 'this_year' ? 'selected' : '' }}>هذه السنة</option>
                                <option value="this_month" {{ $dateType == 'this_month' ? 'selected' : '' }}>هذا الشهر</option>
                                <option value="this_week" {{ $dateType == 'this_week' ? 'selected' : '' }}>هذا الأسبوع</option>
                                <option value="today" {{ $dateType == 'today' ? 'selected' : '' }}>اليوم</option>
                                <option value="custom_date" {{ $dateType == 'custom_date' ? 'selected' : '' }}>تاريخ مخصص</option>
                            </select>
                        </div>

                        <div class="col-sm-6 col-md-3">
                            <label class="mb-2">من</label>
                            <input type="date" name="from" value="{{ $from }}" class="form-control">
                        </div>

                        <div class="col-sm-6 col-md-3">
                            <label class="mb-2">إلى</label>
                            <input type="date" name="to" value="{{ $to }}" class="form-control">
                        </div>

                        <div class="col-sm-6 col-md-3">
                            <label class="mb-2">بحث</label>
                            <input type="text" name="searchValue" value="{{ $searchValue }}" class="form-control" placeholder="اسم البائع / المتجر / البريد">
                        </div>

                        <div class="col-sm-6 col-md-3">
                            <button type="submit" class="btn btn-primary w-100">تصفية</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-3">
                <div class="card card-body">
                    <h6 class="mb-2">إجمالي العمولات</h6>
                    <h3 class="mb-0">{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $statistics['total_commission'])) }}</h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-body">
                    <h6 class="mb-2">العمولات المدفوعة</h6>
                    <h3 class="mb-0 text-success">{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $statistics['total_paid'])) }}</h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-body">
                    <h6 class="mb-2">العمولات غير المدفوعة</h6>
                    <h3 class="mb-0 text-danger">{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $statistics['total_unpaid'])) }}</h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-body">
                    <h6 class="mb-2">عدد البائعين</h6>
                    <h3 class="mb-0">{{ $statistics['total_vendors'] }}</h3>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="d-flex flex-wrap gap-3 align-items-center justify-content-between mb-4">
                    <h3 class="mb-0 mr-auto">
                        قائمة البائعين
                        <span class="badge badge-info text-bg-info fs-12">{{ $vendors->total() }}</span>
                    </h3>
                </div>

                <div class="table-responsive">
                    <table class="table __table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table w-100">
                        <thead class="thead-light thead-50 text-capitalize">
                        <tr>
                            <th>#</th>
                            <th>البائع</th>
                            <th>المتجر</th>
                            <th>إجمالي العمولة</th>
                            <th>المدفوع</th>
                            <th>غير المدفوع</th>
                            <th>عدد الفواتير</th>
                            <th class="text-center">الإجراء</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($vendors as $key => $vendor)
                            <tr>
                                <td>{{ $vendors->firstItem() + $key }}</td>
                                <td>
                                    <div class="d-flex flex-column">
                                        <strong>{{ $vendor->f_name }} {{ $vendor->l_name }}</strong>
                                        <small class="text-muted">{{ $vendor->email }}</small>
                                    </div>
                                </td>
                                <td>{{ $vendor->shop?->name ?? '-' }}</td>
                                <td>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $vendor->total_commission_sum ?? 0)) }}</td>
                                <td class="text-success">{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $vendor->paid_commission_sum ?? 0)) }}</td>
                                <td class="text-danger">{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $vendor->unpaid_commission_sum ?? 0)) }}</td>
                                <td>{{ $vendor->invoices_count ?? 0 }}</td>
                                <td class="text-center">
                                    <a href="{{ route('admin.vendors.commission-invoices.show', $vendor->id) }}"
                                       class="btn btn-outline-primary btn-sm">
                                        تفاصيل
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">لا توجد بيانات عمولات مطابقة.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-end">
                    {{ $vendors->links() }}
                </div>
            </div>
        </div>
    </div>
@endsection
