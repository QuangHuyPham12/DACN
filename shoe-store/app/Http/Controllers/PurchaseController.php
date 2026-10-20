<?php

namespace App\Http\Controllers;

use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\StoreService;
use Illuminate\Http\Request;

class PurchaseController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['status' => 'nullable|in:draft,confirmed', 'q' => 'nullable|string|max:150']);
        $query = PurchaseOrder::with('user')->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))->when($request->query('q'), fn ($q, $term) => $q->where('supplier_name', 'like', '%'.$term.'%'));

        return view('admin.purchases', ['pageTitle' => 'Nhập hàng', 'pageSubtitle' => 'Nhập đúng hàng, cập nhật đúng tồn.', 'purchases' => $query->latest('id')->paginate(15)->withQueryString()]);
    }

    public function create()
    {
        return view('admin.transaction-form', ['pageTitle' => 'Tạo phiếu nhập hàng', 'purchase' => true, 'contacts' => Supplier::orderBy('name')->get(), 'variants' => ProductVariant::with(['product', 'size', 'color'])->orderBy('sku')->get()]);
    }

    public function store(Request $request, StoreService $service)
    {
        $order = $service->createPurchase($request->all(), $request->user());

        return redirect()->route('purchases.show', $order)->with('success', 'Đã tạo phiếu nhập nháp. Xác nhận phiếu để cập nhật kho.');
    }

    public function show(PurchaseOrder $purchase)
    {
        return view('admin.purchase', ['pageTitle' => 'Phiếu nhập '.$purchase->code, 'purchase' => $purchase->load(['items', 'user'])]);
    }

    public function confirm(Request $request, PurchaseOrder $purchase, StoreService $service)
    {
        $service->confirmPurchase($purchase, $request->user());

        return back()->with('success', 'Đã xác nhận nhập hàng và cập nhật tồn kho.');
    }
}
