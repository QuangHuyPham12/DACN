<div class="brand-grid">
@foreach($brands as $brand)
<a class="brand-card" href="{{ route('shop.catalog',['brand_id'=>$brand->id]) }}#bo-suu-tap">
<span class="brand-card-top"><span>{{ str_pad($loop->iteration,2,'0',STR_PAD_LEFT) }} / THE BRANDS</span><x-icon name="arrow"/></span>
<strong class="brand-wordmark">{{ $brand->name }}</strong>
<span class="brand-card-bottom"><span>{{ $brand->catalog_count }} mẫu giày</span><span>{{ $brand->catalog_min !== null ? 'Từ '.number_format($brand->catalog_min,0,',','.').'đ' : 'Đang cập nhật' }}</span></span>
</a>
@endforeach
</div>
