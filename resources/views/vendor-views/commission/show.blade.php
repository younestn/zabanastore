@extends('layouts.vendor.app')

@section('title', 'تفاصيل فاتورة العمولة')

@section('content')
    <div class="content container-fluid">
        <div class="mb-3 d-flex justify-content-between align-items-center gap-2 flex-wrap">
            <h2 class="h1 mb-0 d-flex align-items-center gap-2">
                <i class="tio-money-vs"></i>
                تفاصيل فاتورة العمولة
            </h2>

            <a href="{{ route('vendor.report.commission-invoices') }}" class="btn btn-outline-secondary">
                رجوع
            </a>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="mb-3">الفترة</h5>
                        <div><strong>الشهر / السنة:</strong> {{ sprintf('%02d', $invoice->invoice_month) }}/{{ $invoice->invoice_year }}</div>
                        <div><strong>من:</strong> {{ $invoice->period_start ? $invoice->period_start->format('Y-m-d') : '-' }}</div>
                        <div><strong>إلى:</strong> {{ $invoice->period_end ? $invoice->period_end->format('Y-m-d') : '-' }}</div>
                        <div><strong>عدد الطلبات:</strong> {{ $invoice->orders_count }}</div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="mb-3">الحالة</h5>
                        <div>
                            <strong>حالة الدفع:</strong>
                            @if($invoice->payment_status === 'paid')
                                <span class="badge badge-soft-success">Paid</span>
                            @else
                                <span class="badge badge-soft-danger">Unpaid</span>
                            @endif
                        </div>
                        <div class="mt-2"><strong>تاريخ الدفع:</strong> {{ $invoice->paid_at ? $invoice->paid_at->format('Y-m-d H:i') : '-' }}</div>
                        <div class="mt-2"><strong>ملاحظة الدفع:</strong> {{ $invoice->payment_note ?: '-' }}</div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="mb-3">ملخص العمولة</h5>
                        <div><strong>عمولات الطلبات:</strong> {{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $invoice->order_commission_total)) }}</div>
                        <div><strong>التسويات اليدوية:</strong> {{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $invoice->manual_adjustment_total)) }}</div>
                        <div class="mt-2 fs-5"><strong>الإجمالي النهائي:</strong> {{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $invoice->total_commission)) }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">سجل التسويات اليدوية</h4>
            </div>
            <div class="card-body">
                @if($invoice->adjustments->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                            <tr>
                                <th>#</th>
                                <th>النوع</th>
                                <th>المبلغ</th>
                                <th>السبب</th>
                                <th>التاريخ</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($invoice->adjustments->sortByDesc('id') as $adjustment)
                                <tr>
                                    <td>{{ $adjustment->id }}</td>
                                    <td>
                                        @if($adjustment->adjustment_type === 'add')
                                            <span class="badge badge-soft-success">إضافة</span>
                                        @else
                                            <span class="badge badge-soft-warning">خصم</span>
                                        @endif
                                    </td>
                                    <td>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $adjustment->amount)) }}</td>
                                    <td>{{ $adjustment->reason ?: '-' }}</td>
                                    <td>{{ $adjustment->created_at ? $adjustment->created_at->format('Y-m-d H:i') : '-' }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-4 text-muted">
                        لا توجد تسويات يدوية على هذه الفاتورة.
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
