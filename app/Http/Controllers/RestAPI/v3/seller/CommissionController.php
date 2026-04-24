<?php

namespace App\Http\Controllers\RestAPI\v3\seller;

use App\Events\ChattingEvent;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Chatting;
use App\Models\Seller;
use App\Models\SellerCommissionInvoice;
use App\Models\Shop;
use App\Utils\Helpers;
use App\Utils\ImageManager;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CommissionController extends Controller
{
    public function currentMonthInvoice(Request $request): JsonResponse
    {
        $seller = $request->seller;
        $now = now();

        $invoice = SellerCommissionInvoice::with(['adjustments' => function ($query) {
            $query->latest();
        }])->where('seller_id', $seller->id)
            ->where('invoice_year', $now->year)
            ->where('invoice_month', $now->month)
            ->first();

        if (!$invoice) {
            return response()->json($this->emptyCurrentMonthPayload(), 200);
        }

        return response()->json($this->formatInvoicePayload($invoice), 200);
    }

    public function sendPaymentReceipt(Request $request, int|string $id): JsonResponse
{
    $validator = Validator::make($request->all(), [
        'receipt_image' => 'required|mimes:jpg,jpeg,png,webp|max:5120',
        'note' => 'nullable|string|max:1000',
    ], [
        'receipt_image.required' => 'يرجى اختيار صورة وصل الدفع',
        'receipt_image.mimes' => 'صيغة الصورة غير مدعومة',
        'receipt_image.max' => 'حجم الصورة كبير جدًا. الحد الأقصى 5MB',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => Helpers::validationErrorProcessor($validator)], 403);
    }

    try {
        $seller = $request->seller;

        $invoice = SellerCommissionInvoice::where('seller_id', $seller->id)->find($id);
        if (!$invoice) {
            return response()->json(['message' => 'فاتورة العمولة غير موجودة'], 404);
        }

        if ($invoice->payment_status === 'paid') {
            return response()->json(['message' => 'هذه الفاتورة مدفوعة بالفعل'], 409);
        }

        $attachment = [[
            'file_name' => ImageManager::upload('chatting/', 'webp', $request->file('receipt_image')),
            'storage' => getWebConfig(name: 'storage_connection_type') ?? 'public',
        ]];

        $shopId = Shop::where('seller_id', $seller->id)->value('id');
        $messageForm = Seller::find($seller->id);

        $periodLabel = sprintf('%02d/%04d', $invoice->invoice_month, $invoice->invoice_year);

        $messageLines = [
            'وصل دفع فاتورة العمولة',
            'الفترة: ' . $periodLabel,
            'من: ' . $invoice->period_start->format('Y-m-d'),
            'إلى: ' . $invoice->period_end->format('Y-m-d'),
            'الإجمالي: ' . (float) $invoice->total_commission,
        ];

        if ($request->filled('note')) {
            $messageLines[] = 'ملاحظة: ' . $request->note;
        }

        $chatting = new Chatting();
        $chatting->seller_id = $seller->id;
        $chatting->admin_id = 0;
        $chatting->message = implode(PHP_EOL, $messageLines);
        $chatting->attachment = json_encode($attachment);
        $chatting->sent_by_seller = 1;
        $chatting->seen_by_seller = 1;
        $chatting->seen_by_admin = 0;
        $chatting->shop_id = $shopId;
        $chatting->notification_receiver = 'admin';

        if ($chatting->save()) {
            $admin = new Admin();
            $admin->id = 0;
            $admin->name = translate('admin');

            event(new ChattingEvent(
                key: 'message_from_seller',
                type: 'admin',
                userData: $admin,
                messageForm: $messageForm
            ));

            return response()->json([
                'message' => 'تم إرسال وصل الدفع إلى الإدارة بنجاح'
            ], 200);
        }

        return response()->json([
            'message' => 'فشل إرسال وصل الدفع'
        ], 403);
    } catch (\Throwable $e) {
        report($e);

        return response()->json([
            'message' => 'حدث خطأ أثناء معالجة وصل الدفع'
        ], 500);
    }
}

    private function emptyCurrentMonthPayload(): array
    {
        $now = Carbon::now();

        return [
            'id' => null,
            'invoice_year' => (int)$now->year,
            'invoice_month' => (int)$now->month,
            'period_start' => $now->copy()->startOfMonth()->toDateString(),
            'period_end' => $now->copy()->endOfMonth()->toDateString(),
            'orders_count' => 0,
            'order_commission_total' => 0,
            'manual_adjustment_total' => 0,
            'total_commission' => 0,
            'payment_status' => 'unpaid',
            'paid_at' => null,
            'payment_note' => null,
            'adjustments' => [],
        ];
    }

    private function formatInvoicePayload(SellerCommissionInvoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'invoice_year' => (int)$invoice->invoice_year,
            'invoice_month' => (int)$invoice->invoice_month,
            'period_start' => $invoice->period_start?->format('Y-m-d'),
            'period_end' => $invoice->period_end?->format('Y-m-d'),
            'orders_count' => (int)$invoice->orders_count,
            'order_commission_total' => (float)$invoice->order_commission_total,
            'manual_adjustment_total' => (float)$invoice->manual_adjustment_total,
            'total_commission' => (float)$invoice->total_commission,
            'payment_status' => (string)$invoice->payment_status,
            'paid_at' => $invoice->paid_at?->format('Y-m-d H:i:s'),
            'payment_note' => $invoice->payment_note,
            'adjustments' => $invoice->adjustments->map(function ($adjustment) {
                return [
                    'id' => $adjustment->id,
                    'adjustment_type' => $adjustment->adjustment_type,
                    'amount' => (float)$adjustment->amount,
                    'reason' => $adjustment->reason,
                    'created_at' => $adjustment->created_at?->format('Y-m-d H:i:s'),
                ];
            })->values(),
        ];
    }
}
