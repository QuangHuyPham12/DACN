<!doctype html>
<html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>{{ $pageTitle ?? 'Quản lý cửa hàng' }} · SOLE</title><link rel="stylesheet" href="{{ asset('assets/app.css').'?v='.filemtime(public_path('assets/app.css')) }}"><script src="{{ asset('assets/app.js').'?v='.filemtime(public_path('assets/app.js')) }}" defer></script><link rel="stylesheet" href="{{ asset('assets/admin.css').'?v='.filemtime(public_path('assets/admin.css')) }}"><link rel="stylesheet" href="{{ asset('assets/customer-care.css').'?v='.filemtime(public_path('assets/customer-care.css')) }}">
</head>
<body>
<aside class="sidebar" id="sidebar">
<a class="brand" href="{{ route('dashboard') }}"><span class="brand-mark">s</span><span class="brand-name">sole<small>store manager</small></span></a>
<div class="nav-label">KHÔNG GIAN LÀM VIỆC</div>
@php($nav = [['dashboard','grid','Tổng quan'],['products.index','box','Sản phẩm'],['orders.index','bag','Đơn bán hàng'],['inventory','layers','Tồn kho']])
@foreach($nav as [$route,$icon,$label])<a class="nav-link {{ request()->routeIs($route) || ($route === 'products.index' && request()->routeIs('products.*','variants.*')) || ($route === 'orders.index' && request()->routeIs('orders.*')) ? 'active' : '' }}" href="{{ route($route) }}"><x-icon :name="$icon"/>{{ $label }}</a>@endforeach
@if(auth()->user()->role === 'admin')<a class="nav-link {{ request()->routeIs('purchases.*') ? 'active' : '' }}" href="{{ route('purchases.index') }}"><x-icon name="truck"/>Nhập hàng</a>@endif
<a class="nav-link {{ request()->is('admin/data/customers') || request()->routeIs('customers.show') ? 'active' : '' }}" href="{{ route('data.index','customers') }}"><x-icon name="users"/>Khách hàng</a>
@if(auth()->user()->role === 'admin')
<a class="nav-link {{ request()->routeIs('reports') ? 'active' : '' }}" href="{{ route('reports') }}"><x-icon name="chart"/>Báo cáo doanh thu</a>
<a class="nav-link {{ request()->routeIs('returns.*') ? 'active' : '' }}" href="{{ route('returns.index') }}"><x-icon name="bag"/>Trả hàng & hoàn tiền</a>
<a class="nav-link {{ request()->routeIs('coupons.*') ? 'active' : '' }}" href="{{ route('coupons.index') }}"><x-icon name="tag"/>Mã giảm giá</a>
<a class="nav-link {{ request()->routeIs('adjustments.*') ? 'active' : '' }}" href="{{ route('adjustments.index') }}"><x-icon name="layers"/>Kiểm kê & điều chỉnh</a>
<div class="nav-label">QUẢN LÝ CỬA HÀNG</div>
@foreach(['categories'=>'Loại giày','brands'=>'Thương hiệu','sizes'=>'Kích cỡ','colors'=>'Màu sắc','suppliers'=>'Nhà cung cấp'] as $key=>$label)
<a class="nav-link {{ request()->is('admin/data/'.$key) ? 'active' : '' }}" href="{{ route('data.index',$key) }}"><x-icon :name="$key === 'suppliers' ? 'truck' : 'layers'"/>{{ $label }}</a>
@endforeach
<a class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}" href="{{ route('users.index') }}"><x-icon name="shield"/>Nhân viên</a>
@endif
<a class="nav-link" href="{{ route('care.requests') }}"><x-icon name="users"/>Yêu cầu đổi / trả</a>@if(auth()->user()->role === 'admin')<a class="nav-link" href="{{ route('care.reviews') }}"><x-icon name="tag"/>Đánh giá sản phẩm</a><a class="nav-link" href="{{ route('care.messages') }}"><x-icon name="bag"/>Email đơn hàng</a>@endif
<div class="sidebar-bottom"><a class="store-link" href="{{ route('shop.index') }}"><div><p>Ghé thăm cửa hàng</p><small>Trải nghiệm mua sắm SOLE</small></div><x-icon name="arrow"/></a></div>
</aside>
<div class="admin-main"><header class="topbar"><button class="icon-button mobile-menu" data-menu aria-label="Mở menu" aria-controls="sidebar" aria-expanded="false"><x-icon name="menu"/></button><span class="breadcrumb">Không gian làm việc &nbsp; / &nbsp; <b>{{ $pageTitle ?? 'Tổng quan' }}</b></span><form class="header-search" method="get" action="{{ route('products.index') }}" role="search"><x-icon name="search"/><input name="q" maxlength="200" placeholder="Tìm sản phẩm…" aria-label="Tìm nhanh sản phẩm"></form><div class="user-menu"><a class="avatar" href="{{ route('password.form') }}" aria-label="Đổi mật khẩu">{{ mb_substr(auth()->user()->name,0,1) }}</a><a href="{{ route('password.form') }}"><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->role === 'admin' ? 'Quản trị viên' : 'Nhân viên bán hàng' }}</small></a><form method="post" action="{{ route('logout') }}">@csrf<button class="icon-button" aria-label="Đăng xuất" title="Đăng xuất"><x-icon name="logout"/></button></form></div></header>
<main class="workspace"><div class="page-heading"><div><h1>{{ $pageTitle ?? '' }}</h1>@isset($pageSubtitle)<p>{{ $pageSubtitle }}</p>@endisset</div><div class="actions">@yield('actions')</div></div>@include('partials.alerts')@yield('content')</main></div>
<dialog class="confirmation-dialog" data-confirmation aria-labelledby="confirmation-title" aria-describedby="confirmation-message"><h2 id="confirmation-title">Xác nhận thao tác</h2><p id="confirmation-message" data-confirm-message></p><form method="dialog" class="actions"><button value="cancel" class="btn secondary" autofocus>Quay lại</button><button value="confirm" class="btn">Xác nhận</button></form></dialog>
</body></html>
