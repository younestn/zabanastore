<?php

namespace App\Http\Controllers\Admin\Vendor;

use App\Http\Controllers\BaseController;
use App\Models\BusinessSetting;
use App\Models\Seller;
use App\Models\SellerCommissionAdjustment;
use App\Models\SellerCommissionAlertLog;
use App\Models\SellerCommissionInvoice;
use Carbon\Carbon;
use Devrabiul\ToastMagic\Facades\ToastMagic;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class VendorCommissionController extends BaseController
{
    public function index(?Request $request, string $type = null): View|Collection|LengthAwarePaginator|callable|RedirectResponse|JsonResponse|null
    {
        $dateType = $request['date_type'] ?? 'this_year';
        $from = $request['from'];
        $to = $request['to'];
        $searchValue = $request['searchValue'];

        [$startDate, $endDate] = $this->resolveDateRange($dateType, $from, $to);

        $baseInvoiceQuery = SellerCommissionInvoice::query()
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('period_start', [$startDate, $endDate]);
            });

        $statistics = [
            'total_commission' => (float) (clone $baseInvoiceQuery)->sum('total_commission'),
            'total_paid' => (float) (clone $baseInvoiceQuery)->where('payment_status', 'paid')->sum('total_commission'),
            'total_unpaid' => (float) (clone $baseInvoiceQuery)->where('payment_status', 'unpaid')->sum('total_commission'),
            'total_vendors' => (int) (clone $baseInvoiceQuery)->distinct('seller_id')->count('seller_id'),
        ];

        $vendors = Seller::query()
            ->with(['shop'])
            ->whereHas('commissionInvoices', function ($query) use ($startDate, $endDate) {
                if ($startDate && $endDate) {
                    $query->whereBetween('period_start', [$startDate, $endDate]);
                }
            })
            ->when($searchValue, function ($query) use ($searchValue) {
                $query->where(function ($innerQuery) use ($searchValue) {
                    $innerQuery->where('f_name', 'like', "%{$searchValue}%")
                        ->orWhere('l_name', 'like', "%{$searchValue}%")
                        ->orWhere('email', 'like', "%{$searchValue}%")
                        ->orWhereHas('shop', function ($shopQuery) use ($searchValue) {
                            $shopQuery->where('name', 'like', "%{$searchValue}%");
                        });
                });
            })
            ->withSum(['commissionInvoices as total_commission_sum' => function ($query) use ($startDate, $endDate) {
                if ($startDate && $endDate) {
                    $query->whereBetween('period_start', [$startDate, $endDate]);
                }
            }], 'total_commission')
            ->withSum(['commissionInvoices as paid_commission_sum' => function ($query) use ($startDate, $endDate) {
                $query->where('payment_status', 'paid');
                if ($startDate && $endDate) {
                    $query->whereBetween('period_start', [$startDate, $endDate]);
                }
            }], 'total_commission')
            ->withSum(['commissionInvoices as unpaid_commission_sum' => function ($query) use ($startDate, $endDate) {
                $query->where('payment_status', 'unpaid');
                if ($startDate && $endDate) {
                    $query->whereBetween('period_start', [$startDate, $endDate]);
                }
            }], 'total_commission')
            ->withCount(['commissionInvoices as invoices_count' => function ($query) use ($startDate, $endDate) {
                if ($startDate && $endDate) {
                    $query->whereBetween('period_start', [$startDate, $endDate]);
                }
            }])
            ->orderByDesc(DB::raw('COALESCE(unpaid_commission_sum, 0)'))
            ->paginate(getWebConfig(name: 'pagination_limit'))
            ->appends($request->all());

        $activeThresholdAlerts = $this->getActiveThresholdAlertsForAdmin();

        return view('admin-views.vendor.commission.index', compact(
            'vendors',
            'statistics',
            'activeThresholdAlerts',
            'dateType',
            'from',
            'to',
            'searchValue'
        ));
    }

    public function show(int|string $id): View|RedirectResponse
    {
        $seller = Seller::query()->with('shop')->find($id);

        if (!$seller) {
            ToastMagic::error('البائع غير موجود');
            return back();
        }

        $request = request();
        $dateType = $request['date_type'] ?? 'this_year';
        $from = $request['from'];
        $to = $request['to'];

        [$startDate, $endDate] = $this->resolveDateRange($dateType, $from, $to);

        $invoiceQuery = SellerCommissionInvoice::query()
            ->where('seller_id', $seller->id)
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('period_start', [$startDate, $endDate]);
            });

        $summary = [
            'total_commission' => (float) (clone $invoiceQuery)->sum('total_commission'),
            'total_paid' => (float) (clone $invoiceQuery)->where('payment_status', 'paid')->sum('total_commission'),
            'total_unpaid' => (float) (clone $invoiceQuery)->where('payment_status', 'unpaid')->sum('total_commission'),
            'total_invoices' => (int) (clone $invoiceQuery)->count(),
        ];

        $invoices = SellerCommissionInvoice::query()
            ->with(['adjustments'])
            ->where('seller_id', $seller->id)
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('period_start', [$startDate, $endDate]);
            })
            ->orderByDesc('invoice_year')
            ->orderByDesc('invoice_month')
            ->paginate(12)
            ->appends($request->all());

        $thresholdAmount = (float) (BusinessSetting::where('type', 'seller_commission_threshold_amount')->value('value') ?? 0);

        $latestSellerAlert = SellerCommissionAlertLog::query()
            ->where('seller_id', $seller->id)
            ->where('recipient_type', 'admin')
            ->latest('id')
            ->first();

        $activeThresholdAlert = $latestSellerAlert && $latestSellerAlert->alert_status === 'sent'
            ? $latestSellerAlert
            : null;

        return view('admin-views.vendor.commission.show', compact(
            'seller',
            'summary',
            'invoices',
            'thresholdAmount',
            'activeThresholdAlert',
            'dateType',
            'from',
            'to'
        ));
    }

    public function markAsPaid(Request $request, int|string $id): RedirectResponse
    {
        $invoice = SellerCommissionInvoice::find($id);

        if (!$invoice) {
            ToastMagic::error('فاتورة العمولة غير موجودة');
            return back();
        }

        if ($invoice->payment_status === 'paid') {
            ToastMagic::info('هذه الفاتورة مدفوعة مسبقًا');
            return back();
        }

        $invoice->payment_status = 'paid';
        $invoice->paid_at = now();
        $invoice->paid_by_admin_id = auth('admin')->id();
        $invoice->payment_note = $request['payment_note'];
        $invoice->save();

        $this->syncCommissionThresholdAlertState((int) $invoice->seller_id);

        ToastMagic::success('تم تعليم الفاتورة كمدفوعة بنجاح');
        return back();
    }

    public function addAdjustment(Request $request, int|string $id): RedirectResponse
    {
        $request->validate([
            'adjustment_type' => 'required|in:add,deduct',
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'nullable|string|max:1000',
        ]);

        $invoice = SellerCommissionInvoice::find($id);

        if (!$invoice) {
            ToastMagic::error('فاتورة العمولة غير موجودة');
            return back();
        }

        SellerCommissionAdjustment::create([
            'seller_id' => $invoice->seller_id,
            'seller_commission_invoice_id' => $invoice->id,
            'adjustment_type' => $request['adjustment_type'],
            'amount' => $request['amount'],
            'reason' => $request['reason'],
            'created_by_admin_id' => auth('admin')->id(),
        ]);

        $this->recalculateInvoiceTotals($invoice->fresh());
        $this->syncCommissionThresholdAlertState((int) $invoice->seller_id);

        ToastMagic::success('تم حفظ التسوية اليدوية بنجاح');
        return back();
    }

    private function resolveDateRange(?string $dateType, ?string $from, ?string $to): array
    {
        $dateType = $dateType ?: 'this_year';

        return match ($dateType) {
            'this_month' => [
                Carbon::now()->startOfMonth()->toDateString(),
                Carbon::now()->endOfMonth()->toDateString(),
            ],
            'this_week' => [
                Carbon::now()->startOfWeek()->toDateString(),
                Carbon::now()->endOfWeek()->toDateString(),
            ],
            'today' => [
                Carbon::now()->toDateString(),
                Carbon::now()->toDateString(),
            ],
            'custom_date' => [
                $from ?: null,
                $to ?: null,
            ],
            default => [
                Carbon::now()->startOfYear()->toDateString(),
                Carbon::now()->endOfYear()->toDateString(),
            ],
        };
    }

    private function recalculateInvoiceTotals(SellerCommissionInvoice $invoice): void
    {
        $manualAdjustmentTotal = (float) SellerCommissionAdjustment::query()
            ->where('seller_commission_invoice_id', $invoice->id)
            ->selectRaw("COALESCE(SUM(CASE WHEN adjustment_type = 'add' THEN amount ELSE -amount END), 0) as total")
            ->value('total');

        $invoice->manual_adjustment_total = round($manualAdjustmentTotal, 2);
        $invoice->total_commission = round(
            (float) $invoice->order_commission_total + (float) $invoice->manual_adjustment_total,
            2
        );

        if ($invoice->payment_status === 'paid') {
            $invoice->payment_status = 'unpaid';
            $invoice->paid_at = null;
            $invoice->paid_by_admin_id = null;
            $invoice->payment_note = trim(
                ($invoice->payment_note ? $invoice->payment_note . PHP_EOL : '') .
                'تمت إعادة فتح الفاتورة تلقائيًا بعد إضافة تسوية يدوية.'
            );
        }

        $invoice->save();
    }

    private function getActiveThresholdAlertsForAdmin()
    {
        return SellerCommissionAlertLog::query()
            ->with(['seller.shop'])
            ->where('recipient_type', 'admin')
            ->latest('id')
            ->get()
            ->groupBy('seller_id')
            ->map(function ($group) {
                return $group->first();
            })
            ->filter(function ($alert) {
                return $alert->alert_status === 'sent';
            })
            ->values();
    }

    private function syncCommissionThresholdAlertState(int $sellerId): void
    {
        $thresholdAmount = (float) (BusinessSetting::where('type', 'seller_commission_threshold_amount')->value('value') ?? 0);

        if ($thresholdAmount <= 0) {
            return;
        }

        $unpaidAmount = (float) SellerCommissionInvoice::query()
            ->where('seller_id', $sellerId)
            ->where('payment_status', 'unpaid')
            ->sum('total_commission');

        foreach (['seller', 'admin'] as $recipientType) {
            $latestAlert = SellerCommissionAlertLog::query()
                ->where('seller_id', $sellerId)
                ->where('recipient_type', $recipientType)
                ->latest('id')
                ->first();

            if ($unpaidAmount >= $thresholdAmount) {
                if (!$latestAlert || $latestAlert->alert_status === 'resolved') {
                    SellerCommissionAlertLog::create([
                        'seller_id' => $sellerId,
                        'threshold_amount' => $thresholdAmount,
                        'unpaid_amount' => $unpaidAmount,
                        'recipient_type' => $recipientType,
                        'alert_status' => 'sent',
                        'sent_at' => now(),
                    ]);
                }
            } else {
                if ($latestAlert && $latestAlert->alert_status === 'sent') {
                    SellerCommissionAlertLog::create([
                        'seller_id' => $sellerId,
                        'threshold_amount' => $thresholdAmount,
                        'unpaid_amount' => $unpaidAmount,
                        'recipient_type' => $recipientType,
                        'alert_status' => 'resolved',
                        'sent_at' => now(),
                    ]);
                }
            }
        }
    }
}
