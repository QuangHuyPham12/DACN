@extends('layouts.shop')
@section('title','Thanh toán '.$order->code.' · SOLE')
@push('head')
<link rel="stylesheet" href="{{ asset('assets/payment.css') }}">
<script src="{{ asset('assets/vendor/qrcode.js') }}" defer></script>
<script src="{{ asset('assets/payment.js') }}" defer></script>
@endpush
@section('content')
<div class="shop-content payment-page">
    <div class="checkout-steps payment-steps"><span>✓ Thông tin nhận hàng</span><strong>02 Thanh toán</strong><span>03 Theo dõi giao hàng</span></div>
    <div class="payment-heading"><span class="eyebrow">ONE STEP CLOSER</span><h1>Đôi giày đã gần<br>bạn hơn một bước<span>.</span></h1><p>Đơn {{ $order->code }} · {{ $order->items->sum('quantity') }} đôi giày · {{ $order->created_at->format('d/m/Y H:i') }}</p></div>
    <div class="payment-grid">
        <section class="payment-card">
            <div class="payment-card-heading"><span class="eyebrow">{{ $order->payment_method === 'bank_transfer' ? 'CHUYỂN KHOẢN NGÂN HÀNG' : 'THANH TOÁN KHI NHẬN HÀNG' }}</span><span class="badge">{{ $order->payment_label }}</span></div>
            @if($order->status === 'cancelled')
                <h2>{{ $order->expired_at ? 'Đơn đã hết hạn thanh toán.' : 'Đơn hàng đã hủy.' }}</h2><p>Không chuyển thêm tiền cho đơn này.</p>
                @if($order->payment)<div class="info-note spaced">{{ $order->payment->refunded_at ? 'Cửa hàng đã xác nhận hoàn lại toàn bộ tiền.' : 'Cửa hàng đang xử lý hoàn lại toàn bộ tiền đã thu.' }} Số tiền: <strong>{{ number_format($order->payment->amount,0,',','.') }}đ</strong>.</div>@endif
            @elseif($order->payment)
                <div class="payment-success"><x-icon name="check"/></div><h2>Cửa hàng đã nhận đủ tiền.</h2><p>Thanh toán được xác nhận lúc {{ $order->payment->paid_at->format('d/m/Y H:i') }}. Bạn có thể tiếp tục theo dõi hành trình của đôi giày.</p><div class="payment-amount">{{ number_format($order->payment->amount,0,',','.') }}<small> VND</small></div>
            @elseif($order->payment_method === 'cod')
                <div class="payment-success"><x-icon name="bag"/></div><h2>Nhận giày, rồi thanh toán.</h2><p>Cửa hàng sẽ liên hệ xác nhận và chuẩn bị đơn. Thanh toán cho nhân viên giao hàng khi nhận giày.</p><div class="payment-amount">{{ number_format($order->total,0,',','.') }}<small> VND</small></div><div class="info-note spaced">Bạn đã hoàn thành bước đặt hàng. Chưa thu tiền.</div>
            @elseif($payload)
                @if($order->payment_expires_at && !$order->transfer_reported_at)<div class="payment-expiry"><strong>Hạn thanh toán: {{ $order->payment_expires_at->format('H:i d/m/Y') }}</strong><br>Đơn chưa báo chuyển khoản và chưa được cửa hàng xác nhận sẽ tự hủy sau thời hạn. Không chuyển tiền khi đơn đã hết hạn.</div>@endif<h2>Quét mã. Sẵn sàng nhận giày.</h2><p>Mở ứng dụng ngân hàng, chọn quét QR. Kiểm tra người nhận, số tiền và nội dung trước khi xác nhận.</p>
                <div class="qr-payment-block"><div><div class="qr-frame" data-qr-payload="{{ $payload }}" data-order-code="{{ $order->code }}"><p>Đang tạo mã QR…</p></div><button class="qr-download" type="button" data-download-qr hidden>Tải mã QR ↓</button><noscript>Bật JavaScript để hiện QR hoặc dùng thông tin chuyển khoản bên cạnh.</noscript></div><div class="bank-details"><span class="eyebrow">TỔNG CẦN CHUYỂN</span><div class="payment-amount">{{ number_format($order->total,0,',','.') }}<small> VND</small></div><dl><div><dt>Ngân hàng</dt><dd>{{ $order->bank_snapshot['bank'] }}</dd></div><div><dt>Chủ tài khoản</dt><dd>{{ $order->bank_snapshot['name'] }}</dd></div><div><dt>Số tài khoản</dt><dd><strong>{{ $order->bank_snapshot['number'] }}</strong><button type="button" data-copy-value="{{ $order->bank_snapshot['number'] }}" aria-label="Sao chép số tài khoản">Sao chép</button></dd></div><div><dt>Nội dung chuyển khoản</dt><dd><strong>{{ $reference }}</strong><button type="button" data-copy-value="{{ $reference }}" aria-label="Sao chép nội dung chuyển khoản">Sao chép</button></dd></div></dl></div></div>
                <p class="copy-feedback" data-copy-feedback role="status" aria-live="polite"></p>
                @if($order->transfer_reported_at)<div class="info-note"><strong>Đã báo chuyển khoản · Chờ đối soát</strong><p>Thông báo lúc {{ $order->transfer_reported_at->format('d/m/Y H:i') }}. Chỉ chuyển một lần; cửa hàng sẽ xác nhận sau khi kiểm tra đã nhận đủ tiền.</p></div>@else<form method="post" action="{{ route('shop.transfer.report',$order->tracking_token) }}">@csrf<button class="btn full-width">Tôi đã chuyển khoản <x-icon name="arrow"/></button></form><small class="payment-disclaimer">Nút này gửi thông báo cho cửa hàng. Việc quét mã hoặc bấm nút không tự xác nhận đã thanh toán.</small>@endif
            @else
                <h2>Chưa có mã thanh toán.</h2><div class="info-note">Thông tin ngân hàng của đơn chưa hợp lệ. Vui lòng liên hệ cửa hàng trước khi chuyển tiền.</div>
            @endif
        </section>
        <aside class="payment-summary"><span class="eyebrow">ĐƠN HÀNG CỦA BẠN</span><h2>{{ $order->code }}</h2><ul>@foreach($order->items as $item)<li><div><strong>{{ $item->product_name }}</strong><small>{{ $item->variant_label }} · SL {{ $item->quantity }}</small></div><span>{{ number_format($item->quantity*$item->unit_price,0,',','.') }}đ</span></li>@endforeach</ul>@include('partials.order-totals')<div class="payment-recipient"><small>GIAO ĐẾN</small><strong>{{ $order->customer_name }} · {{ $order->customer_phone }}</strong><p>{{ $order->shipping_address }}</p></div><a class="btn full-width" href="{{ route('shop.order',$order->tracking_token) }}">Theo dõi giao hàng <x-icon name="arrow"/></a><a class="payment-refresh" href="{{ route('shop.payment',$order->tracking_token) }}">↻ Cập nhật thanh toán</a><small>Lưu liên kết đơn hàng để xem lại. Liên kết riêng tư chứa thông tin nhận hàng.</small></aside>
    </div>
</div>
@endsection
