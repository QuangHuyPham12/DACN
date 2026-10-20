<?php

namespace App\Http\Controllers;

use App\Models\OrderMessage;
use App\Models\ProductReview;
use App\Models\SupportRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerCareAdminController extends Controller
{
    public function reviews(Request $request)
    {
        $request->validate(['status' => 'nullable|in:pending,approved,rejected']);

        return view('admin.reviews', ['pageTitle' => 'Đánh giá sản phẩm', 'reviews' => ProductReview::with(['product', 'account.customer', 'order'])->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))->latest('id')->paginate(15)->withQueryString()]);
    }

    public function moderate(Request $request, ProductReview $review)
    {
        $data = $request->validate(['status' => 'required|in:approved,rejected', 'content_hash' => 'required|string|size:64']);
        DB::transaction(function () use ($request, $review, $data) {
            $locked = ProductReview::whereKey($review->id)->lockForUpdate()->firstOrFail();
            if (! hash_equals($locked->contentHash(), $data['content_hash'])) {
                throw ValidationException::withMessages(['review' => 'Khách vừa sửa đánh giá. Hãy tải lại trang và đọc nội dung mới trước khi duyệt.']);
            }
            $locked->update(['status' => $data['status'], 'moderated_by' => $request->user()->id, 'moderated_at' => now()]);
        }, 3);

        return back()->with('success', 'Đã cập nhật trạng thái đánh giá.');
    }

    public function requests(Request $request)
    {
        $request->validate(['status' => 'nullable|in:pending,reviewing,approved,rejected,resolved']);

        return response()->view('admin.support-requests', ['pageTitle' => 'Yêu cầu đổi / trả', 'tickets' => SupportRequest::with(['order.saleReturn', 'account.customer'])->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))->latest('id')->paginate(12)->withQueryString()])->header('Cache-Control', 'private, no-store');
    }

    public function respond(Request $request, SupportRequest $ticket)
    {
        $data = $request->validate(['status' => 'required|in:reviewing,approved,rejected,resolved', 'staff_reply' => 'required|string|min:10|max:2000']);
        DB::transaction(function () use ($request, $ticket, $data) {
            $locked = SupportRequest::lockForUpdate()->findOrFail($ticket->id);
            $allowed = ['pending' => ['reviewing', 'approved', 'rejected'], 'reviewing' => ['reviewing', 'approved', 'rejected'], 'approved' => ['approved', 'resolved', 'rejected'], 'rejected' => ['reviewing'], 'resolved' => []];
            if (! in_array($data['status'], $allowed[$locked->status], true)) {
                throw ValidationException::withMessages(['status' => 'Không thể chuyển sang trạng thái này.']);
            }
            if ($data['status'] === 'resolved' && ! $locked->order->saleReturn) {
                throw ValidationException::withMessages(['status' => 'Quản trị viên cần ghi nhận nhận hàng và hoàn tiền bằng phiếu trả trước khi đóng yêu cầu. Đổi size cần lập đơn mới sau khi thống nhất.']);
            }
            $locked->update($data + ['handled_by' => $request->user()->id, 'handled_at' => now()]);
        }, 3);

        return back()->with('success', 'Đã phản hồi yêu cầu. Khách hàng có thể xem trong tài khoản.');
    }

    public function messages()
    {
        return response()->view('admin.messages', ['pageTitle' => 'Email đơn hàng', 'messages' => OrderMessage::with('order')->latest('id')->paginate(20), 'localMail' => in_array(config('mail.default'), ['log', 'array'])])->header('Cache-Control', 'private, no-store');
    }

    public function retry(OrderMessage $message)
    {
        OrderMessage::whereKey($message->id)->whereIn('status', ['failed', 'preview'])->update(['status' => 'pending', 'attempts' => 0, 'attempted_at' => null, 'processed_at' => null]);

        return back()->with('success', 'Đã đưa email về hàng đợi; tác vụ nền sẽ xử lý.');
    }
}
