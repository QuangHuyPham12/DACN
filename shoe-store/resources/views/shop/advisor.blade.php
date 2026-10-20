<div class="shoe-advisor" data-advisor data-endpoint="{{ route('advisor.message') }}" data-product-id="{{ request()->routeIs('shop.product') ? request()->route('product')->id : '' }}">
<button type="button" class="advisor-launcher" aria-controls="shoe-advisor-panel" aria-expanded="false" data-advisor-toggle><span class="advisor-launcher-symbol" aria-hidden="true">✦</span><span>Tư vấn chọn giày<small>SOLE Assistant</small></span><span class="advisor-online" aria-hidden="true"></span></button>
<section class="advisor-panel" id="shoe-advisor-panel" role="dialog" aria-labelledby="advisor-title" hidden>
<header class="advisor-header"><span class="advisor-avatar" aria-hidden="true">s<span>✦</span></span><div><h2 id="advisor-title">SOLE Assistant</h2><span data-advisor-mode>{{ config('advisor.api_key') ? 'AI · Có tra cứu danh mục' : 'Tư vấn theo danh mục · Chưa kết nối AI' }}</span></div><button type="button" data-advisor-close aria-label="Đóng tư vấn"><x-icon name="close"/></button></header>
<div class="advisor-privacy">{{ config('advisor.api_key') ? 'Khi gửi, nội dung chat và sản phẩm liên quan được chuyển tới OpenAI để tư vấn. Không gửi thông tin cá nhân, mật khẩu hoặc thanh toán.' : 'Tra cứu sản phẩm ngay tại cửa hàng, không gửi chat tới dịch vụ AI. Không nhập thông tin cá nhân hoặc mật khẩu.' }}</div>
<div class="advisor-messages" role="log" aria-live="polite" aria-relevant="additions" aria-label="Cuộc trò chuyện tư vấn" data-advisor-messages></div>
<div class="advisor-criteria" data-advisor-criteria hidden></div>
<div class="advisor-suggestions" data-advisor-suggestions></div>
<div class="advisor-status" role="status" data-advisor-status></div>
<form class="advisor-form" data-advisor-form><label class="advisor-input-label" for="advisor-message">Câu hỏi tư vấn giày</label><div><textarea id="advisor-message" name="message" rows="2" maxlength="1000" placeholder="Ví dụ: Nike size 40 dưới 1 triệu…" required data-advisor-input></textarea><button type="submit" aria-label="Gửi câu hỏi" data-advisor-send><x-icon name="arrow"/></button></div></form>
<footer class="advisor-footer"><button type="button" data-advisor-clear>Bắt đầu lại</button><a href="{{ route('shop.guide') }}#chon-size">Bảng size ↗</a><a href="{{ route('shop.catalog') }}">Xem sản phẩm ↗</a></footer>
</section><noscript><a class="advisor-nojs" href="{{ route('shop.guide') }}">Xem hướng dẫn chọn giày</a></noscript>
</div>
