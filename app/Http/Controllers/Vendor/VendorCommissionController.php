<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\BaseController;
use App\Models\BusinessSetting;
use App\Models\SellerCommissionAlertLog;
use App\Models\SellerCommissionInvoice;
use Devrabiul\ToastMagic\Facades\ToastMagic;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
class VendorCommissionController extends BaseController
{
public function index(?Request $request, string $type = null): View|Collection|LengthAwarePaginator|callable|RedirectResponse|JsonResponse|null
    {
        $sellerId = auth('seller')->id();
        $searchValue = $request['searchValue'];
        $paymentStatus = $request['payment_status'];
        $invoiceYear = $request['invoice_year'];
        $invoiceMonth = $request['invoice_month'];

        $invoices = SellerCommissionInvoice::query()
            ->where('seller_id', $sellerId)
            ->when($searchValue, function ($query) use ($searchValue) {
                $query->where(function ($innerQuery) use ($searchValue) {
                    $innerQuery->where('invoice_year', 'like', "%{$searchValue}%")
                        ->orWhere('invoice_month', 'like', "%{$searchValue}%");
                });
            })
            ->when($paymentStatus && $paymentStatus !== 'all', function ($query) use ($paymentStatus) {
                $query->where('payment_status', $paymentStatus);
            })
            ->when($invoiceYear, function ($query) use ($invoiceYear) {
                $query->where('invoice_year', $invoiceYear);
            })
            ->when($invoiceMonth, function ($query) use ($invoiceMonth) {
                $query->where('invoice_month', $invoiceMonth);
            })
            ->orderByDesc('invoice_year')
            ->orderByDesc('invoice_month')
            ->paginate(getWebConfig(name: 'pagination_limit'))
            ->appends($request->all());

        $statistics = [
            'total_unpaid' => (float) SellerCommissionInvoice::query()
                ->where('seller_id', $sellerId)
                ->where('payment_status', 'unpaid')
                ->sum('total_commission'),

            'total_paid' => (float) SellerCommissionInvoice::query()
                ->where('seller_id', $sellerId)
                ->where('payment_status', 'paid')
                ->sum('total_commission'),

            'unpaid_count' => (int) SellerCommissionInvoice::query()
                ->where('seller_id', $sellerId)
                ->where('payment_status', 'unpaid')
                ->count(),

            'paid_count' => (int) SellerCommissionInvoice::query()
                ->where('seller_id', $sellerId)
                ->where('payment_status', 'paid')
                ->count(),
        ];

        $thresholdAmount = (float) (BusinessSetting::where('type', 'seller_commission_threshold_amount')->value('value') ?? 0);

        $latestThresholdAlert = SellerCommissionAlertLog::query()
            ->where('seller_id', $sellerId)
            ->where('recipient_type', 'seller')
            ->latest('id')
            ->first();

        $activeThresholdAlert = $latestThresholdAlert && $latestThresholdAlert->alert_status === 'sent'
            ? $latestThresholdAlert
            : null;

        return view('vendor-views.commission.index', compact(
            'invoices',
            'statistics',
            'thresholdAmount',
            'activeThresholdAlert',
            'searchValue',
            'paymentStatus',
            'invoiceYear',
            'invoiceMonth',
        ));
    }

    public function show(int|string $id): View|RedirectResponse
    {
        $invoice = SellerCommissionInvoice::query()
            ->with(['adjustments'])
            ->where('seller_id', auth('seller')->id())
            ->find($id);

        if (!$invoice) {
            ToastMagic::error(translate('invoice_not_found'));
            return back();
        }

        return view('vendor-views.commission.show', compact('invoice'));
    }
}
