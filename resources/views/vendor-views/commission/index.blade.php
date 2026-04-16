@extends('layouts.vendor.app')

@section('title', 'فواتير العمولات')

@section('content')
    <div class="content container-fluid">
        <div class="mb-3">
            <h2 class="h1 mb-0 d-flex align-items-center gap-2">
                <i class="tio-money-vs"></i>
                فواتير العمولات الشهرية
            </h2>
        </div>

        @if($activeThresholdAlert)
            <div class="alert alert-warning d-flex flex-column gap-2 mb-4">
                <strong>تنبيه عمولة غير مسددة</strong>
                <span>
                    لقد تجاوز إجمالي العمولات غير المسددة الحد المحدد.
                    الحد: {{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $activeThresholdAlert->threshold_amount)) }}
                    —
                    غير المسدد الحالي: {{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $activeThresholdAlert->unpaid_amount)) }}
                </span>
            </div>
        @endif

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card h-100">
                    <div class="card-body">
                        <h6 class="mb-2">إجمالي غير المسدد</h6>
                        <h3 class="mb-0">{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $statistics['total_unpaid'])) }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100">
                    <div class="card-body">
                        <h6 class="mb-2">إجمالي المسدد</h6>
                        <h3 class="mb-0">{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $statistics['total_paid'])) }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100">
                    <div class="card-body">
                        <h6 class="mb-2">عدد الفواتير غير المسددة</h6>
                        <h3 class="mb-0">{{ $statistics['unpaid_count'] }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100">
                    <div class="card-body">
                        <h6 class="mb-2">حد التنبيه</h6>
                        <h3 class="mb-0">{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $thresholdAmount)) }}</h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <form action="{{ route('vendor.report.commission-invoices') }}" method="GET">
                    <div class="row g-3">
                        <div class="col-lg-3">
                            <label class="form-label">بحث</label>
                            <input type="text" name="searchValue" value="{{ $searchValue }}" class="form-control" placeholder="شهر / سنة">
                        </div>

                        <div class="col-lg-3">
                            <label class="form-label">الحالة</label>
                            <select name="payment_status" class="form-control">
                                <option value="all">الكل</option>
                                <option value="paid" {{ $paymentStatus === 'paid' ? 'selected' : '' }}>Paid</option>
                                <option value="unpaid" {{ $paymentStatus === 'unpaid' ? 'selected' : '' }}>Unpaid</option>
                            </select>
                        </div>

                        <div class="col-lg-3">
                            <label class="form-label">السنة</label>
                            <input type="number" name="invoice_year" value="{{ $invoiceYear }}" class="form-control" placeholder="2026">
                        </div>

                        <div class="col-lg-3">
                            <label class="form-label">الشهر</label>
                            <input type="number" min="1" max="12" name="invoice_month" value="{{ $invoiceMonth }}" class="form-control" placeholder="4">
                        </div>

                        <div class="col-12 d-flex gap-2">
                            <button type="submit" class="btn btn-primary">تصفية</button>
                            <a href="{{ route('vendor.report.commission-invoices') }}" class="btn btn-outline-secondary">إعادة تعيين</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                @if($invoices->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                            <tr>
                                <th>الفترة</th>
                                <th>عدد الطلبات</th>
                                <th>عمولات الطلبات</th>
                                <th>التسويات اليدوية</th>
                                <th>الإجمالي</th>
                                <th>الحالة</th>
                                <th>تاريخ الدفع</th>
                                <th class="text-center">الإجراء</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($invoices as $invoice)
                                <tr>
                                    <td>{{ sprintf('%02d', $invoice->invoice_month) }}/{{ $invoice->invoice_year }}</td>
                                    <td>{{ $invoice->orders_count }}</td>
                                    <td>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $invoice->order_commission_total)) }}</td>
                                    <td>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $invoice->manual_adjustment_total)) }}</td>
                                    <td>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $invoice->total_commission)) }}</td>
                                    <td>
                                        @if($invoice->payment_status === 'paid')
                                            <span class="badge badge-soft-success">Paid</span>
                                        @else
                                            <span class="badge badge-soft-danger">Unpaid</span>
                                        @endif
                                    </td>
                                    <td>{{ $invoice->paid_at ? $invoice->paid_at->format('Y-m-d H:i') : '-' }}</td>
                                    <td class="text-center">
                                        <a href="{{ route('vendor.report.commission-invoices.show', $invoice->id) }}"
                                           class="btn btn-outline-primary btn-sm">
                                            عرض
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-end">
                        {{ $invoices->links() }}
                    </div>
                @else
                    <div class="text-center py-4 text-muted">
                        لا توجد فواتير عمولات حتى الآن.
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
