@extends('layouts.admin.app')

@section('title', 'تفاصيل عمولات البائع')

@section('content')
    <div class="content container-fluid">
        <div class="mb-3 d-flex justify-content-between align-items-center gap-2 flex-wrap">
            <h2 class="h1 mb-0 d-flex align-items-center gap-2">
                <img width="20" src="{{ dynamicAsset(path: 'public/assets/back-end/img/earning_report.png') }}" alt="">
                تفاصيل عمولات البائع
            </h2>

            <a href="{{ route('admin.vendors.commission-invoices.index') }}" class="btn btn-outline-secondary">
                رجوع
            </a>
        </div>

        @if($activeThresholdAlert)
            <div class="alert alert-warning d-flex flex-column gap-2 mb-4">
                <strong>تنبيه تجاوز الحد</strong>
                <span>
                    الحد: {{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $thresholdAmount)) }}
                    —
                    غير المسدد الحالي: {{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $activeThresholdAlert->unpaid_amount)) }}
                </span>
            </div>
        @endif

        <div class="row g-3 mb-4">
            <div class="col-lg-3">
                <div class="card card-body">
                    <h6 class="mb-2">البائع</h6>
                    <h4 class="mb-1">{{ $seller->f_name }} {{ $seller->l_name }}</h4>
                    <div class="text-muted">{{ $seller->email }}</div>
                    <div class="text-muted">{{ $seller->shop?->name ?? '-' }}</div>
                </div>
            </div>
            <div class="col-lg-3">
                <div class="card card-body">
                    <h6 class="mb-2">إجمالي العمولة</h6>
                    <h3 class="mb-0">{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $summary['total_commission'])) }}</h3>
                </div>
            </div>
            <div class="col-lg-3">
                <div class="card card-body">
                    <h6 class="mb-2">المدفوع</h6>
                    <h3 class="mb-0 text-success">{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $summary['total_paid'])) }}</h3>
                </div>
            </div>
            <div class="col-lg-3">
                <div class="card card-body">
                    <h6 class="mb-2">غير المدفوع</h6>
                    <h3 class="mb-0 text-danger">{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $summary['total_unpaid'])) }}</h3>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <form action="{{ route('admin.vendors.commission-invoices.show', $seller->id) }}" method="GET">
                    <div class="row gy-3 gx-2 align-items-end">
                        <div class="col-sm-6 col-md-3">
                            <label class="mb-2">الفترة</label>
                            <select class="form-select" name="date_type">
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
                            <button type="submit" class="btn btn-primary w-100">تصفية</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h3 class="mb-4">الفواتير الشهرية</h3>

                <div class="table-responsive">
                    <table class="table table-hover table-borderless align-middle">
                        <thead class="thead-light">
                        <tr>
                            <th>الفترة</th>
                            <th>عدد الطلبات</th>
                            <th>عمولات الطلبات</th>
                            <th>التسويات اليدوية</th>
                            <th>الإجمالي</th>
                            <th>الحالة</th>
                            <th>ملاحظة الدفع</th>
                            <th class="text-center">التسديد</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($invoices as $invoice)
                            <tr>
                                <td>{{ sprintf('%02d', $invoice->invoice_month) }}/{{ $invoice->invoice_year }}</td>
                                <td>{{ $invoice->orders_count }}</td>
                                <td>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $invoice->order_commission_total)) }}</td>
                                <td>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $invoice->manual_adjustment_total)) }}</td>
                                <td>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $invoice->total_commission)) }}</td>
                                <td>
                                    @if($invoice->payment_status === 'paid')
                                        <span class="badge badge-success text-bg-success">Paid</span>
                                    @else
                                        <span class="badge badge-danger text-bg-danger">Unpaid</span>
                                    @endif
                                </td>
                                <td>{{ $invoice->payment_note ?: '-' }}</td>
                                <td class="text-center">
                                    @if($invoice->payment_status !== 'paid')
                                        <form action="{{ route('admin.vendors.commission-invoices.mark-as-paid', $invoice->id) }}" method="POST" class="mb-2">
                                            @csrf
                                            <input type="hidden" name="payment_note" value="تم التسديد من لوحة إدارة العمولات">
                                            <button type="submit" class="btn btn-success btn-sm">
                                                Mark as Paid
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-success fw-semibold">مسدد</span>
                                    @endif
                                </td>
                            </tr>

                            <tr>
                                <td colspan="8">
                                    <div class="border rounded p-3 bg-light">
                                        <form action="{{ route('admin.vendors.commission-invoices.adjustments.store', $invoice->id) }}" method="POST">
                                            @csrf
                                            <div class="row g-2 align-items-end">
                                                <div class="col-md-3">
                                                    <label class="mb-2">نوع التسوية</label>
                                                    <select name="adjustment_type" class="form-control">
                                                        <option value="add">إضافة</option>
                                                        <option value="deduct">خصم</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="mb-2">المبلغ</label>
                                                    <input type="number" step="0.01" min="0.01" name="amount" class="form-control" required>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="mb-2">السبب</label>
                                                    <input type="text" name="reason" class="form-control" placeholder="سبب التسوية">
                                                </div>
                                                <div class="col-md-2">
                                                    <button type="submit" class="btn btn-primary w-100">حفظ</button>
                                                </div>
                                            </div>
                                        </form>

                                        @if($invoice->adjustments->count() > 0)
                                            <div class="mt-3">
                                                <strong class="d-block mb-2">آخر التعديلات:</strong>
                                                <ul class="mb-0">
                                                    @foreach($invoice->adjustments->sortByDesc('id')->take(3) as $adjustment)
                                                        <li>
                                                            {{ $adjustment->adjustment_type === 'add' ? 'إضافة' : 'خصم' }}
                                                            — {{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $adjustment->amount)) }}
                                                            — {{ $adjustment->reason ?: 'بدون سبب' }}
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">لا توجد فواتير عمولات لهذا البائع ضمن الفلتر الحالي.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-end">
                    {{ $invoices->links() }}
                </div>
            </div>
        </div>
    </div>
@endsection
