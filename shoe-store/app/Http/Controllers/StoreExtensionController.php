<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use App\Models\ProductVariant;
use App\Models\SalesOrder;
use App\Models\SalesReturn;
use App\Services\StoreService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreExtensionController extends Controller
{
    public function coupons()
    {
        return view('admin.coupons', ['pageTitle' => 'Mã giảm giá', 'pageSubtitle' => 'Tạo ưu đãi với thời hạn và số lượt sử dụng rõ ràng.', 'coupons' => Coupon::latest('id')->paginate(12)]);
    }

    public function saveCoupon(Request $request, ?Coupon $coupon = null)
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        $data = $request->validate(['code' => ['required', 'string', 'max:40', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('coupons')->ignore($coupon?->id)], 'name' => 'required|string|max:150', 'type' => 'required|in:fixed,percent', 'value' => 'required|integer|min:1|max:1000000000', 'minimum_order' => 'required|integer|min:0|max:1000000000', 'maximum_discount' => 'nullable|integer|min:1|max:1000000000', 'usage_limit' => 'nullable|integer|min:1|max:1000000', 'starts_at' => 'nullable|date_format:Y-m-d', 'ends_at' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('starts_at'), 'after_or_equal:starts_at')], 'is_active' => 'required|boolean']);
        if ($data['type'] === 'percent' && $data['value'] > 100) {
            return back()->withErrors(['value' => 'Phần trăm giảm phải từ 1 đến 100.'])->withInput();
        }
        ($coupon ?? new Coupon)->fill($data)->save();

        return back()->with('success', 'Đã lưu mã giảm giá.');
    }

    public function adjustmentForm()
    {
        return view('admin.adjustments', ['pageTitle' => 'Điều chỉnh & kiểm kê kho', 'variants' => ProductVariant::with(['product', 'size', 'color'])->orderBy('sku')->get(), 'adjustments' => DB::table('inventory_adjustments')->join('product_variants', 'product_variants.id', '=', 'inventory_adjustments.product_variant_id')->join('users', 'users.id', '=', 'inventory_adjustments.user_id')->select('inventory_adjustments.*', 'product_variants.sku', 'users.name as user_name')->orderByDesc('inventory_adjustments.id')->paginate(15), 'requestToken' => (string) Str::uuid()]);
    }

    public function adjust(Request $request, StoreService $service)
    {
        $service->adjustInventory($request->all(), $request->user());

        return redirect()->route('adjustments.index')->with('success', 'Đã ghi nhận phiếu điều chỉnh và lịch sử kho.');
    }

    public function returns()
    {
        return view('admin.returns', ['pageTitle' => 'Trả hàng & hoàn tiền', 'pageSubtitle' => 'Theo dõi phiếu trả toàn bộ đơn hàng và số tiền đã hoàn.', 'returns' => SalesReturn::with(['order', 'user'])->latest('id')->paginate(15)]);
    }

    public function returnOrder(Request $request, SalesOrder $order, StoreService $service)
    {
        $data = $request->validate(['reason' => 'required|string|min:5|max:500', 'restock' => 'required|boolean']);
        $service->returnSale($order, $request->user(), $data['reason'], (bool) $data['restock']);

        return back()->with('success', 'Đã ghi nhận trả hàng và xác nhận hoàn tiền.');
    }

    public function delivery(Request $request, SalesOrder $order, StoreService $service)
    {
        $data = $request->validate(['status' => 'required|in:confirmed,packing,shipping,delivered,delivery_failed,returned_to_store', 'tracking_number' => 'nullable|string|max:100']);
        $service->advanceDelivery($order, $request->user(), $data['status'], $data['tracking_number'] ?? null);

        return back()->with('success', 'Đã cập nhật tiến trình giao hàng.');
    }

    public function export(Request $request, string $type)
    {
        abort_unless(in_array($type, ['orders', 'inventory']), 404);
        $request->validate(['from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from']);

        return response()->streamDownload(function () use ($type, $request) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            $safe = fn ($v) => is_string($v) && preg_match('/^[\s]*[=+@-]/u', $v) ? "'".$v : $v;
            if ($type === 'inventory') {
                fputcsv($handle, ['SKU', 'Sản phẩm', 'Size / Màu', 'Tồn sổ sách', 'Giữ chỗ', 'Có thể bán', 'Giá bán'], ',', '"', '');
                foreach (ProductVariant::with(['product', 'size', 'color'])->orderBy('id')->cursor() as $v) {
                    fputcsv($handle, array_map($safe, [$v->sku, $v->product->name, $v->label, $v->stock, $v->reserved_stock, $v->available_stock, $v->price]), ',', '"', '');
                }
            } else {
                fputcsv($handle, ['Mã đơn', 'Ngày hoàn tất', 'Khách hàng', 'Kênh', 'Tiền hàng', 'Giảm giá', 'Phí giao', 'Đã thu', 'Đã hoàn tiền hàng'], ',', '"', '');
                $orders = SalesOrder::with('saleReturn')->where('status', 'completed')->when($request->filled('from'), fn ($q) => $q->where('completed_at', '>=', $request->input('from').' 00:00:00'))->when($request->filled('to'), fn ($q) => $q->where('completed_at', '<=', $request->input('to').' 23:59:59'))->orderBy('id');
                foreach ($orders->cursor() as $o) {
                    fputcsv($handle, array_map($safe, [$o->code, $o->completed_at?->format('Y-m-d H:i:s'), $o->customer_name, $o->channel, $o->subtotal, $o->discount, $o->shipping_fee, $o->total, $o->saleReturn?->refund_amount ?? 0]), ',', '"', '');
                }
            }
            fclose($handle);
        }, 'sole-'.$type.'-'.today()->toDateString().'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }
}
