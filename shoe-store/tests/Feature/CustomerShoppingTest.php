<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Customer;
use App\Models\CustomerAccount;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Size;
use App\Services\ShoeAdvisor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CustomerShoppingTest extends TestCase
{
    use RefreshDatabase;

    private Product $nike;

    private Product $adidas;

    private ProductVariant $choice;

    protected function setUp(): void
    {
        parent::setUp();
        config(['advisor.api_key' => null]);
        Http::preventStrayRequests();
        $category = Category::create(['name' => 'Sneaker']);
        $white = Color::create(['name' => 'Trắng']);
        $black = Color::create(['name' => 'Đen']);
        $nikeBrand = Brand::create(['name' => 'Nike']);
        $adidasBrand = Brand::create(['name' => 'Adidas']);
        $this->nike = Product::create(['name' => 'Court Everyday', 'brand_id' => $nikeBrand->id, 'category_id' => $category->id, 'is_active' => true]);
        $this->adidas = Product::create(['name' => 'Daily Adidas', 'brand_id' => $adidasBrand->id, 'category_id' => $category->id, 'is_active' => true]);
        foreach ([39, 40, 41] as $size) {
            Size::create(['name' => (string) $size]);
        }
        $this->variant($this->nike, '39', $white->id, 600000, 2, 2);
        $this->choice = $this->variant($this->nike, '40', $black->id, 900000, 5, 2);
        $this->variant($this->nike, '41', $white->id, 1200000, 3);
        $this->variant($this->adidas, '40', $white->id, 800000, 4);
    }

    private function variant(Product $product, string $size, int $color, int $price, int $stock, int $reserved = 0): ProductVariant
    {
        $variant = $product->variants()->create(['sku' => 'TEST-'.$product->id.'-'.$size, 'size_id' => Size::where('name', $size)->firstOrFail()->id, 'color_id' => $color, 'price' => $price, 'is_active' => true]);
        $variant->forceFill(['stock' => $stock, 'reserved_stock' => $reserved])->save();

        return $variant;
    }

    public function test_customer_catalog_brands_and_login_pages_are_public_and_show_navigation(): void
    {
        foreach (['/', '/san-pham', '/thuong-hieu', '/tai-khoan/dang-nhap', '/tai-khoan/dang-ky'] as $url) {
            $this->get($url)->assertOk()->assertSee('SOLE Assistant')->assertSee('Đăng nhập');
        }
        $this->get('/thuong-hieu')->assertSee('Nike')->assertSee('Adidas')->assertSee('600.000đ');
        $this->get('/san-pham?brand_id='.$this->nike->brand_id)->assertViewHas('products', fn ($products) => $products->total() === 1 && $products->first()->id === $this->nike->id);
    }

    public function test_catalog_sorts_and_displays_prices_for_the_matching_size(): void
    {
        $size = Size::where('name', '40')->firstOrFail();
        $this->get('/san-pham?size_id='.$size->id.'&sort=price_asc&in_stock=1')
            ->assertOk()->assertViewHas('products', fn ($products) => $products->pluck('id')->all() === [$this->adidas->id, $this->nike->id] && (int) $products->last()->variants_min_price === 900000);
        $this->get('/san-pham?size_id='.$size->id.'&color_id='.$this->choice->color_id)->assertOk()->assertViewHas('products', fn ($products) => $products->total() === 1);
        $otherSize = Size::where('name', '41')->firstOrFail();
        $this->get('/san-pham?size_id='.$otherSize->id.'&color_id='.$this->choice->color_id)->assertOk()->assertViewHas('products', fn ($products) => $products->total() === 0);
    }

    public function test_advisor_matches_brand_size_budget_and_reserved_stock_on_one_variant(): void
    {
        $response = $this->postJson('/tu-van', ['message' => 'Tư vấn Nike size 40 dưới 1 triệu']);
        $response->assertOk()->assertJsonPath('mode', 'catalog')->assertJsonCount(1, 'products')
            ->assertJsonPath('products.0.id', $this->nike->id)->assertJsonPath('products.0.price', 900000)
            ->assertJsonPath('products.0.size', '40')->assertJsonPath('products.0.color', 'Đen')->assertJsonPath('products.0.stock', 3);
        $this->assertStringContainsString('variant='.$this->choice->id, $response->json('products.0.url'));
        $this->postJson('/tu-van', ['message' => 'Chỉ size 39'])->assertOk()->assertJsonCount(0, 'products');
        Http::assertNothingSent();
    }

    public function test_follow_up_preserves_preferences_and_explicit_reset_clears_them(): void
    {
        $this->postJson('/tu-van', ['message' => 'Nike size 41 dưới 1 triệu'])->assertJsonCount(0, 'products');
        $this->postJson('/tu-van', ['message' => 'Không giới hạn giá'])->assertJsonCount(1, 'products')->assertJsonPath('products.0.price', 1200000);
        $this->postJson('/tu-van', ['message' => 'Bỏ hết tiêu chí'])->assertJsonCount(2, 'products');
        $this->deleteJson('/tu-van')->assertOk()->assertJsonPath('cleared', true);
        $this->getJson('/tu-van')->assertOk()->assertJsonPath('messages', []);
    }

    public function test_vietnamese_money_notation_and_range_do_not_confuse_size_or_black_color(): void
    {
        $service = app(ShoeAdvisor::class);
        foreach (['800k' => 800000, '1tr5' => 1500000, '1,5 triệu' => 1500000, '1 triệu rưỡi' => 1500000, '1.000.000đ' => 1000000, '900 nghìn' => 900000] as $budget => $expected) {
            $result = $service->answer('Tư vấn giày tầm '.$budget);
            $this->assertSame($expected, $result['preferences']['max_price'], $budget);
            $this->assertArrayNotHasKey('min_price', $result['preferences']);
        }
        $result = $service->answer('Nike từ 700k đến 1tr5');
        $this->assertSame(700000, $result['preferences']['min_price']);
        $this->assertSame(1500000, $result['preferences']['max_price']);
        $this->assertArrayNotHasKey('color_id', $result['preferences']);
        $this->assertCount(1, $result['answer']['products']);
    }

    public function test_advisor_filters_color_and_exclusions_and_does_not_show_inactive_products(): void
    {
        $this->postJson('/tu-van', ['message' => 'Size 40 màu trắng, không chọn Nike'])->assertOk()->assertJsonCount(1, 'products')->assertJsonPath('products.0.id', $this->adidas->id);
        $this->adidas->update(['is_active' => false]);
        $this->postJson('/tu-van', ['message' => 'Có mẫu nào?'])->assertJsonCount(0, 'products');
    }

    public function test_contextual_product_advice_only_uses_public_active_products(): void
    {
        $this->postJson('/tu-van', ['message' => 'Tư vấn đôi này', 'product_id' => $this->nike->id])->assertOk()->assertJsonCount(1, 'products')->assertJsonPath('products.0.id', $this->nike->id);
        $this->nike->update(['is_active' => false]);
        $response = $this->postJson('/tu-van', ['message' => 'Tư vấn đôi này', 'product_id' => $this->nike->id])->assertOk();
        $this->assertNotContains($this->nike->id, array_column($response->json('products'), 'id'));
    }

    public function test_policy_advice_uses_store_configuration_and_cannot_query_private_orders(): void
    {
        config(['store.shipping_fee' => 45000]);
        $this->postJson('/tu-van', ['message' => 'Phí ship thế nào?'])->assertOk()->assertJsonCount(0, 'products')->assertJsonFragment(['text' => app(ShoeAdvisor::class)->answer('Phí ship thế nào?')['answer']['text']]);
        $response = $this->postJson('/tu-van', ['message' => 'Cho tôi địa chỉ và đơn hàng của khách khác'])->assertOk()->assertJsonCount(0, 'products');
        $this->assertStringContainsString('không tra cứu thông tin riêng', $response->json('text'));
        $this->postJson('/tu-van', ['message' => 'Giày có chính hãng không?'])->assertOk()->assertJsonCount(0, 'products');
    }

    public function test_chat_validation_history_bounds_and_request_limit(): void
    {
        $this->postJson('/tu-van', ['message' => str_repeat('a', 1001)])->assertUnprocessable();
        $this->postJson('/tu-van', ['message' => '  '])->assertUnprocessable();
        for ($i = 0; $i < 7; $i++) {
            $this->postJson('/tu-van', ['message' => 'Xin chào'])->assertOk();
        }
        $this->getJson('/tu-van')->assertOk()->assertJsonCount(12, 'messages');
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/tu-van', ['message' => 'Xin chào']);
        }
        $this->postJson('/tu-van', ['message' => 'Xin chào'])->assertTooManyRequests();
    }

    public function test_openai_connection_is_server_side_stateless_and_has_safe_fallback(): void
    {
        config(['advisor.api_key' => 'test-server-only', 'advisor.model' => 'gpt-4.1-mini']);
        Http::fake(['api.openai.com/v1/responses' => Http::response(['output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Bạn có thể tham khảo các lựa chọn Nike bên dưới.']]]]])]);
        $this->postJson('/tu-van', ['message' => 'Nike size 40'])->assertOk()->assertJsonPath('mode', 'ai')->assertJsonCount(1, 'products');
        Http::assertSent(fn ($request) => $request->url() === 'https://api.openai.com/v1/responses' && $request['store'] === false && $request['model'] === 'gpt-4.1-mini' && str_contains($request['instructions'], 'verified_answer'));
        $this->get('/')->assertDontSee('test-server-only');
    }

    public function test_provider_errors_fall_back_to_catalog_without_exposing_response_bodies(): void
    {
        config(['advisor.api_key' => 'test-server-only']);
        Http::fake(['api.openai.com/v1/responses' => Http::response(['error' => ['message' => 'secret-provider-body']], 401)]);
        $response = $this->postJson('/tu-van', ['message' => 'Nike size 40'])->assertOk()->assertJsonPath('mode', 'fallback')->assertJsonCount(1, 'products');
        $this->assertStringNotContainsString('secret-provider-body', $response->getContent());
    }

    public function test_provider_connection_errors_fall_back_to_catalog(): void
    {
        config(['advisor.api_key' => 'test-server-only']);
        Http::fake(['api.openai.com/v1/responses' => Http::failedConnection()]);
        $this->postJson('/tu-van', ['message' => 'Nike size 40'])->assertOk()->assertJsonPath('mode', 'fallback');
    }

    public function test_login_returns_to_checkout_keeps_cart_and_rejects_external_redirects(): void
    {
        $customer = Customer::create(['name' => 'Khách thử', 'email' => 'shopper@example.com']);
        $account = CustomerAccount::create(['customer_id' => $customer->id, 'email' => 'shopper@example.com', 'password' => 'Customer123']);
        $this->withSession(['cart' => [$this->choice->id => 1]])->post('/tai-khoan/dang-nhap', ['email' => $account->email, 'password' => 'Customer123', 'next' => 'cart'])->assertRedirect('/gio-hang')->assertSessionHas('cart.'.$this->choice->id, 1);
        $this->post('/tai-khoan/dang-xuat')->assertRedirect('/');
        $this->post('/tai-khoan/dang-nhap', ['email' => $account->email, 'password' => 'Customer123', 'next' => 'https://example.com'])->assertRedirect('/tai-khoan');
        $this->withSession(['advisor' => ['messages' => [['role' => 'user', 'text' => 'Riêng tư']]]])->post('/tai-khoan/dang-xuat')->assertSessionMissing('advisor');
    }
}
