@extends('layouts.shop')
@section('title','Thương hiệu giày · SOLE Footwear')
@section('content')
<div class="shop-content"><div class="catalog-intro"><span class="eyebrow">FIND YOUR FAVORITE BRAND</span><h1>Hãng bạn thích.<br>Phong cách bạn chọn.</h1><p>Khám phá từng thương hiệu với số mẫu và mức giá hiện có. Chọn hãng rồi lọc thêm size, màu và ngân sách phù hợp.</p><a class="text-link" href="{{ route('shop.catalog') }}">Xem tất cả sản phẩm ↗</a></div>
@include('shop.brand-grid')
<div class="advisor-banner"><div><span class="eyebrow">CHƯA BIẾT BẮT ĐẦU TỪ ĐÂU?</span><h2>Để SOLE giúp bạn chọn.</h2><p>Cho biết ngân sách, hãng và size. Trợ lý sẽ tra các lựa chọn đang còn hàng.</p></div><button type="button" class="btn" data-open-advisor>Tư vấn chọn giày <x-icon name="arrow"/></button></div></div>
@endsection
