@extends('layouts.shop')
@section('title',($reset ? 'Đặt lại mật khẩu' : 'Quên mật khẩu').' · SOLE')
@section('content')
<section class="shop-content recovery-page"><span class="eyebrow">TRỞ LẠI KHÔNG GIAN CỦA BẠN</span><h1>{{ $reset ? 'Một khởi đầu mới.' : 'Quên mật khẩu?' }}</h1><p>{{ $reset ? 'Chọn mật khẩu mới để bảo vệ tài khoản của bạn.' : 'Nhập email đã đăng ký. Chúng tôi sẽ gửi liên kết giúp bạn đăng nhập trở lại.' }}</p>
<form class="card card-body spaced" method="post" action="{{ route($reset ? 'account.password.reset' : 'account.password.email') }}">@csrf
@if($reset)<input type="hidden" name="token" value="{{ $token }}">@endif
<div class="field"><label for="recovery-email">Email tài khoản</label><input id="recovery-email" name="email" type="email" value="{{ old('email',$email ?? '') }}" maxlength="255" autocomplete="email" required></div>
@if($reset)<div class="field"><label for="recovery-password">Mật khẩu mới</label><input id="recovery-password" name="password" type="password" minlength="8" autocomplete="new-password" required><small>Ít nhất 8 ký tự, gồm chữ và số.</small></div><div class="field"><label for="recovery-confirm">Nhập lại mật khẩu mới</label><input id="recovery-confirm" name="password_confirmation" type="password" minlength="8" autocomplete="new-password" required></div>@endif
<button class="btn full-width">{{ $reset ? 'Lưu mật khẩu mới' : 'Gửi liên kết khôi phục' }}</button></form>
<a class="back-link" href="{{ route('account.login') }}">← Trở về đăng nhập</a>@if($reset)<a class="text-link" href="{{ route('account.password.request') }}">Yêu cầu liên kết mới</a>@endif</section>
@endsection
