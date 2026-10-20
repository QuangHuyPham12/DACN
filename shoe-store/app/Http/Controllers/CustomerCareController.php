<?php

namespace App\Http\Controllers;

use App\Models\CustomerAccount;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\SalesOrder;
use App\Models\SupportRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerCareController extends Controller
{
    public function purchasedOrders(Product $product)
    {
        return SalesOrder::where('customer_id', Auth::guard('customer')->user()?->customer_id)->where('status', 'completed')
            ->whereHas('items.variant', fn ($q) => $q->where('product_id', $product->id));
    }

    public function review(Request $request, Product $product)
    {
        abort_unless($product->is_active, 404);
        $data = $request->validate(['rating' => 'required|integer|between:1,5', 'body' => 'required|string|min:10|max:2000']);
        $account = Auth::guard('customer')->user();
        DB::transaction(function () use ($product, $account, $data) {
            // Serializes two simultaneous submissions from the same account.
            CustomerAccount::whereKey($account->id)->lockForUpdate()->firstOrFail();
            $order = $this->purchasedOrders($product)->latest('id')->first();
            if (! $order) {
                throw ValidationException::withMessages(['review' => 'Chỉ khách đã mua sản phẩm trong đơn hoàn tất mới có thể đánh giá.']);
            }
            ProductReview::updateOrCreate(['product_id' => $product->id, 'customer_account_id' => $account->id], $data + ['sales_order_id' => $order->id, 'status' => 'pending', 'moderated_by' => null, 'moderated_at' => null]);
        }, 3);

        return redirect()->to(route('shop.product', $product).'#danh-gia')->with('success', 'Đã gửi đánh giá. Nội dung sẽ hiển thị sau khi cửa hàng duyệt.');
    }

    public function form(SalesOrder $order)
    {
        $this->owned($order);

        return response()->view('shop.support-request', ['order' => $order->load(['items', 'saleReturn']), 'ticket' => SupportRequest::where('sales_order_id', $order->id)->first()])->header('Cache-Control', 'private, no-store');
    }

    private function owned(SalesOrder $order): void
    {
        abort_unless($order->customer_id === Auth::guard('customer')->user()->customer_id, 404);
    }

    public function store(Request $request, SalesOrder $order)
    {
        $this->owned($order);
        $data = $request->validate(['kind' => 'required|in:exchange,return', 'reason' => 'required|string|min:15|max:2000', 'desired_size' => 'nullable|required_if:kind,exchange|string|max:30']);
        DB::transaction(function () use ($order, $data) {
            $locked = SalesOrder::lockForUpdate()->findOrFail($order->id);
            if ($locked->status !== 'completed' || $locked->saleReturn) {
                throw ValidationException::withMessages(['order' => 'Chỉ gửi yêu cầu cho đơn hoàn tất chưa được ghi nhận trả hàng.']);
            }
            if (SupportRequest::where('sales_order_id', $locked->id)->exists()) {
                throw ValidationException::withMessages(['order' => 'Đơn đã có yêu cầu hỗ trợ. Hãy theo dõi phản hồi tại đây.']);
            }
            SupportRequest::create($data + ['sales_order_id' => $locked->id, 'customer_account_id' => Auth::guard('customer')->id()]);
        }, 3);

        return back()->with('success', 'Đã gửi yêu cầu. Cửa hàng sẽ kiểm tra và phản hồi tại trang này.');
    }
}
