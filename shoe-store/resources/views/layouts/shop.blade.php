<!doctype html>
<html lang="vi"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="referrer" content="same-origin"><meta name="description" content="@yield('description','Khám phá bộ sưu tập giày SOLE. Chọn size, màu sắc và đặt hàng trực tuyến với giá niêm yết rõ ràng.')"><meta name="theme-color" content="#f7f5f0"><title>@yield('title','SOLE Footwear · Mỗi bước chân, một dấu ấn')</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<link rel="icon" href="{{ asset('assets/favicon.svg') }}" type="image/svg+xml">
<link rel="stylesheet" href="{{ asset('assets/app.css').'?v='.filemtime(public_path('assets/app.css')) }}">
<link rel="stylesheet" href="{{ asset('assets/storefront.css').'?v='.filemtime(public_path('assets/storefront.css')) }}">
<link rel="stylesheet" href="{{ asset('assets/advisor.css').'?v='.filemtime(public_path('assets/advisor.css')) }}">
<script src="{{ asset('assets/advisor.js').'?v='.filemtime(public_path('assets/advisor.js')) }}" defer></script>
<script src="{{ asset('assets/app.js').'?v='.filemtime(public_path('assets/app.js')) }}" defer></script><script src="{{ asset('assets/storefront.js').'?v='.filemtime(public_path('assets/storefront.js')) }}" defer></script>
@stack('head')
<link rel="stylesheet" href="{{ asset('assets/customer-care.css').'?v='.filemtime(public_path('assets/customer-care.css')) }}">
</head><body class="shop-page">
<a href="#main-content" class="skip-link">Đến nội dung chính</a>
<div class="shop-top"><span>ĐÔI GIÀY MỚI. HÀNH TRÌNH MỚI.</span><span>Miễn phí giao hàng từ {{ number_format(config('store.free_shipping_from'),0,',','.') }}đ ↗</span></div>
<div class="shop-container"><header class="shop-nav">
<a class="wordmark" href="{{ route('shop.index') }}" aria-label="SOLE · Trang chủ">sole<span>®</span><small>FOOTWEAR & EVERYDAY</small></a>
<nav class="shop-nav-links" id="shop-menu" aria-label="Điều hướng chính"><a class="{{ request()->routeIs('shop.index') ? 'current' : '' }}" href="{{ route('shop.index') }}">Trang chủ</a><a class="{{ request()->routeIs('shop.catalog','shop.product') ? 'current' : '' }}" href="{{ route('shop.catalog') }}">Sản phẩm</a><a class="{{ request()->routeIs('shop.brands') ? 'current' : '' }}" href="{{ route('shop.brands') }}">Thương hiệu</a><a href="{{ route('shop.guide') }}">Hướng dẫn</a></nav>
<div class="shop-nav-actions"><a href="{{ route('account.index') }}#yeu-thich" class="shop-icon nav-favorite" aria-label="Sản phẩm yêu thích"><x-icon name="heart"/></a>@if(Auth::guard('customer')->check())<a href="{{ route('account.index') }}" class="customer-entry" aria-label="Tài khoản của bạn"><x-icon name="user"/><span>Tài khoản</span></a>@else<a href="{{ route('account.login') }}" class="customer-entry"><x-icon name="user"/><span>Đăng nhập</span></a>@endif<a class="cart-link shop-icon" href="{{ route('shop.cart') }}" aria-label="Giỏ hàng, {{ array_sum(session('cart',[])) }} sản phẩm"><x-icon name="bag"/><span class="cart-count">{{ array_sum(session('cart',[])) }}</span></a><button type="button" class="shop-menu-toggle shop-icon" data-shop-menu aria-label="Mở điều hướng" aria-controls="shop-menu" aria-expanded="false"><x-icon name="menu"/></button></div>
</header><main id="main-content">@include('partials.alerts')@yield('content')</main></div>
<footer class="shop-footer" id="ve-sole"><div class="shop-container"><div class="footer-statement"><span>GOOD SHOES.<br>GREAT DAYS.</span><a href="{{ route('shop.index') }}#bo-suu-tap" class="round-link" aria-label="Khám phá bộ sưu tập"><x-icon name="arrow"/></a></div>
<div class="footer-grid"><div><a class="wordmark" href="{{ route('shop.index') }}">sole<span>®</span></a><p>Đôi giày vừa vặn với phong cách.<br>Đồng hành cùng những ngày của bạn.</p></div><div><h3>Khám phá</h3><a href="{{ route('shop.index') }}#bo-suu-tap">Tất cả sản phẩm</a><a href="{{ route('shop.index',['sort'=>'newest']) }}#bo-suu-tap">Bộ sưu tập mới</a><a href="{{ route('account.index') }}#yeu-thich">Danh sách yêu thích</a></div><div><h3>Đồng hành cùng bạn</h3><a href="{{ route('shop.guide') }}#chon-size">Hướng dẫn chọn size</a><a href="{{ route('shop.guide') }}#giao-hang">Giao hàng & thanh toán</a><a href="{{ route('shop.guide') }}#doi-tra">Hỗ trợ đổi trả</a></div><div><h3>Không gian của bạn</h3><a href="{{ route('account.index') }}">Tài khoản & đơn hàng</a><a href="{{ route('shop.cart') }}">Giỏ hàng của bạn</a><a href="{{ route('login') }}">Dành cho nhân viên ↗</a></div></div>
<div class="shop-footer-bottom"><span>© {{ date('Y') }} SOLE Footwear · Đồ án nhóm 6</span><span>PHP · JavaScript · MySQL</span><span>Việt Nam / VND</span></div></div></footer>
<dialog class="confirmation-dialog" data-confirmation aria-labelledby="confirmation-title" aria-describedby="confirmation-message"><h2 id="confirmation-title">Xác nhận thao tác</h2><p id="confirmation-message" data-confirm-message></p><form method="dialog" class="actions"><button value="cancel" class="btn secondary" autofocus>Quay lại</button><button value="confirm" class="btn">Xác nhận</button></form></dialog>
@include('shop.advisor')
</body></html>
