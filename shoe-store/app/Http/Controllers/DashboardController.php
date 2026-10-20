<?php

namespace App\Http\Controllers;

use App\Models\InventoryMovement;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\SalesReturn;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $admin = $request->user()->role === 'admin';

        return view('admin.dashboard', [
            'pageTitle' => 'Tổng quan cửa hàng', 'pageSubtitle' => 'Mỗi bước chân, một hành trình mới.',
            'productCount' => Product::where('is_active', true)->count(),
            'stockCount' => ProductVariant::sum('stock'),
            'pendingCount' => SalesOrder::whereIn('status', ['draft', 'pending'])->count(),
            'todayCount' => SalesOrder::where('status', 'completed')->whereDate('completed_at', today())->count(),
            'revenue' => $admin ? SalesOrder::where('status', 'completed')->whereDate('completed_at', today())->sum('total') - SalesReturn::whereDate('created_at', today())->sum('refund_amount') : null,
            'orders' => SalesOrder::with('user')->latest()->limit(7)->get(),
            'lowStock' => ProductVariant::with(['product', 'size', 'color'])->where('is_active', true)->whereHas('product', fn ($q) => $q->where('is_active', true))->whereRaw('stock - reserved_stock <= low_stock_threshold')->orderBy('stock')->limit(5)->get(),
            'daily' => $admin ? collect(range(6, 0))->map(fn ($days) => ['label' => today()->subDays($days)->format('d/m'), 'value' => (int) SalesOrder::where('status', 'completed')->whereDate('completed_at', today()->subDays($days))->sum('total') - (int) SalesReturn::whereDate('created_at', today()->subDays($days))->sum('refund_amount')]) : collect(),
        ]);
    }

    public function inventory(Request $request)
    {
        $request->validate(['q' => 'nullable|string|max:200', 'low' => 'nullable|in:1', 'variant' => 'nullable|integer|exists:product_variants,id']);
        $query = ProductVariant::with(['product.brand', 'size', 'color']);
        if ($q = $request->query('q')) {
            $query->where(fn ($b) => $b->where('sku', 'like', '%'.$q.'%')->orWhereHas('product', fn ($p) => $p->where('name', 'like', '%'.$q.'%')));
        }
        if ($request->query('low')) {
            $query->whereRaw('stock - reserved_stock <= low_stock_threshold');
        }
        $movements = InventoryMovement::with(['variant.product', 'user'])->when($request->query('variant'), fn ($q, $id) => $q->where('product_variant_id', $id))->latest('id')->paginate(12, ['*'], 'history_page')->withQueryString();

        return view('admin.inventory', ['pageTitle' => 'Tồn kho', 'pageSubtitle' => 'Tồn sổ sách, lượng giữ chỗ và số có thể bán theo từng SKU.', 'variants' => $query->orderBy('stock')->paginate(15)->withQueryString(), 'movements' => $movements]);
    }

    public function reports(Request $request)
    {
        $request->validate(['from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from']);
        $from = $request->input('from', today()->startOfMonth()->toDateString());
        $to = $request->input('to', today()->toDateString());
        if ($to < $from) {
            return back()->withErrors(['to' => 'Ngày kết thúc phải sau ngày bắt đầu.']);
        }
        $orders = SalesOrder::where('status', 'completed')->whereBetween('completed_at', [$from.' 00:00:00', $to.' 23:59:59']);
        $top = SalesOrderItem::query()->join('sales_orders', 'sales_orders.id', '=', 'sales_order_items.sales_order_id')
            ->where('sales_orders.status', 'completed')->whereBetween('completed_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->selectRaw('sales_order_items.product_variant_id, sales_order_items.sku, MAX(sales_order_items.product_name) as product_name, SUM(quantity) as units, SUM(quantity * unit_price) as revenue')
            ->groupBy('sales_order_items.product_variant_id', 'sales_order_items.sku')->orderByDesc('units')->limit(10)->get();

        $refunds = SalesReturn::whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])->sum('refund_amount');
        $receipts = Payment::whereBetween('paid_at', [$from.' 00:00:00', $to.' 23:59:59'])->sum('amount');
        $cancelRefunds = Payment::whereBetween('refunded_at', [$from.' 00:00:00', $to.' 23:59:59'])->sum('amount');
        $pendingRefunds = Payment::whereNull('refunded_at')->whereIn('sales_order_id', SalesOrder::where('status', 'cancelled')->select('id'))->sum('amount');
        $prepayments = Payment::whereIn('sales_order_id', SalesOrder::where('status', 'pending')->select('id'))->sum('amount');

        return view('admin.reports', ['pageTitle' => 'Báo cáo doanh thu', 'pageSubtitle' => 'Đối chiếu tiền bán hàng và tiền hoàn theo ngày ghi nhận.', 'from' => $from, 'to' => $to, 'refunds' => $refunds, 'net' => (clone $orders)->sum('total') - $refunds, 'discounts' => (clone $orders)->sum('discount'), 'shipping' => (clone $orders)->sum('shipping_fee'),
            'receipts' => $receipts, 'cancelRefunds' => $cancelRefunds, 'cashNet' => $receipts - $cancelRefunds - $refunds, 'prepayments' => $prepayments, 'pendingRefunds' => $pendingRefunds,
            'revenue' => (clone $orders)->sum('total'), 'count' => (clone $orders)->count(), 'online' => (clone $orders)->where('channel', 'online')->sum('total'),
            'orders' => $orders->latest('completed_at')->paginate(20)->withQueryString(), 'top' => $top]);
    }
}
