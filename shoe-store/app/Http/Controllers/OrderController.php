<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\ProductVariant;
use App\Models\SalesOrder;
use App\Services\StoreService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['q' => 'nullable|string|max:150', 'status' => 'nullable|in:draft,pending,completed,cancelled', 'channel' => 'nullable|in:pos,online']);
        $query = SalesOrder::with('user');
        if ($q = $request->query('q')) {
            $query->where(fn ($b) => $b->where('customer_name', 'like', '%'.$q.'%')->orWhere('id', preg_replace('/[^0-9]/', '', $q)));
        }
        foreach (['status', 'channel'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->query($field));
            }
        }

        return view('admin.orders', ['pageTitle' => 'Đơn bán hàng', 'pageSubtitle' => 'Theo dõi đơn tại quầy và đơn đặt trực tuyến.', 'orders' => $query->latest('id')->paginate(15)->withQueryString()]);
    }

    public function create()
    {
        return view('admin.transaction-form', ['pageTitle' => 'Tạo đơn bán tại quầy', 'purchase' => false, 'contacts' => Customer::orderBy('name')->get(), 'variants' => ProductVariant::with(['product', 'size', 'color'])->where('is_active', true)->whereHas('product', fn ($q) => $q->where('is_active', true))->orderBy('sku')->get()]);
    }

    public function store(Request $request, StoreService $service)
    {
        $order = $service->createSale($request->all(), $request->user());

        return redirect()->route('orders.show', $order)->with('success', 'Đã tạo đơn nháp. Tồn kho chỉ giảm khi hoàn tất thanh toán.');
    }

    public function show(SalesOrder $order)
    {
        return view('admin.order', ['pageTitle' => 'Đơn hàng '.$order->code, 'order' => $order->load(['items', 'payment', 'user', 'events.user', 'saleReturn.user'])]);
    }

    public function complete(Request $request, SalesOrder $order, StoreService $service)
    {
        $data = $request->validate(['method' => 'required|in:cash,bank_transfer']);
        $service->completeSale($order, $request->user(), $data['method']);

        return back()->with('success', 'Đơn đã hoàn tất. Thanh toán và tồn kho đã được ghi nhận.');
    }

    public function cancel(SalesOrder $order, StoreService $service)
    {
        $service->cancelSale($order);

        return back()->with('success', 'Đã hủy đơn hàng.');
    }

    public function verifyTransfer(Request $request, SalesOrder $order, StoreService $service)
    {
        $data = $request->validate(['amount' => 'required|integer|min:1', 'reference' => 'required|string|min:4|max:100']);
        $service->verifyTransfer($order, $request->user(), (int) $data['amount'], $data['reference']);

        return back()->with('success', 'Đã đối soát nhận tiền chuyển khoản. Kiểm tra trạng thái giao hàng hoặc hoàn tiền nếu đơn đã hủy.');
    }

    public function refundCancelled(Request $request, SalesOrder $order, StoreService $service)
    {
        $data = $request->validate(['reference' => 'required|string|min:4|max:100']);
        $service->refundCancelled($order, $request->user(), $data['reference']);

        return back()->with('success', 'Đã ghi nhận hoàn toàn bộ tiền cho đơn bị hủy.');
    }

    public function shipment(Request $request, SalesOrder $order, StoreService $service)
    {
        $service->updateShipment($order, $request->user(), $request->only(['shipping_carrier', 'tracking_number', 'delivery_location', 'delivery_note']));

        return back()->with('success', 'Đã cập nhật thông tin vận chuyển trên trang theo dõi của khách.');
    }
}
