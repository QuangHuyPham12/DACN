<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Size;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['q' => 'nullable|string|max:200', 'category_id' => 'nullable|integer', 'status' => 'nullable|in:0,1']);
        $query = Product::with(['category', 'brand', 'images'])->withSum('variants', 'stock')->withMin('variants', 'price')->withCount('variants');
        if ($q = $request->query('q')) {
            $query->where('name', 'like', '%'.$q.'%');
        }
        if ($id = $request->query('category_id')) {
            $query->where('category_id', $id);
        }
        if ($request->filled('status')) {
            $query->where('is_active', $request->query('status'));
        }

        return view('admin.products', ['pageTitle' => 'Sản phẩm', 'pageSubtitle' => 'Bộ sưu tập và biến thể của cửa hàng.', 'products' => $query->latest('id')->paginate(12)->withQueryString(), 'categories' => Category::all()]);
    }

    public function form(?Product $product = null)
    {
        $product?->load(['variants.size', 'variants.color', 'images']);

        return view('admin.product-form', ['pageTitle' => $product?->exists ? 'Chỉnh sửa sản phẩm' : 'Thêm sản phẩm', 'product' => $product,
            'categories' => Category::all(), 'brands' => Brand::all(), 'sizes' => Size::all(), 'colors' => Color::all()]);
    }

    public function save(Request $request, ?Product $product = null)
    {
        $data = $request->validate(['name' => 'required|string|max:200', 'description' => 'nullable|string|max:5000', 'category_id' => 'required|exists:categories,id', 'brand_id' => 'required|exists:brands,id', 'is_active' => 'required|boolean', 'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096']);
        unset($data['image']);
        $details = $request->validate(['material' => 'nullable|string|max:255', 'fit_note' => 'nullable|string|max:500', 'care_note' => 'nullable|string|max:2000', 'size_note' => 'nullable|string|max:2000', 'images' => 'nullable|array|max:8', 'images.*' => 'required|image|mimes:jpg,jpeg,png,webp|max:4096']);
        unset($details['images']);
        $data = array_merge($data, $details);
        $files = array_merge($request->hasFile('image') ? [$request->file('image')] : [], $request->file('images', []));
        $product ??= new Product;
        $storedPaths = [];
        try {
            DB::transaction(function () use (&$product, $data, $files, &$storedPaths) {
                if ($product->exists) {
                    $product = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
                }
                if (($product->exists ? $product->images()->count() : 0) + count($files) > 8) {
                    throw ValidationException::withMessages(['images' => 'Mỗi sản phẩm tối đa 8 ảnh. Xóa bớt ảnh cũ trước khi thêm.']);
                }
                $product->fill($data)->save();
                $position = (int) $product->images()->max('sort_order');
                foreach ($files as $file) {
                    $path = $file->store('product-images', 'public');
                    if ($path === false) {
                        throw new \RuntimeException('Không thể lưu ảnh sản phẩm.');
                    }
                    $storedPaths[] = $path;
                    $product->images()->create(['path' => $path, 'sort_order' => ++$position]);
                }
            });
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($storedPaths);
            throw $exception;
        }

        return redirect()->route('products.edit', $product)->with('success', 'Đã lưu sản phẩm. Thêm biến thể để bắt đầu nhập hàng.');
    }

    public function variant(Request $request, Product $product, ?ProductVariant $variant = null)
    {
        if ($variant) {
            abort_unless($variant->product_id === $product->id, 404);
        }
        $data = $request->validate(['sku' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('product_variants')->ignore($variant?->id)], 'size_id' => ['required', 'exists:sizes,id', Rule::unique('product_variants')->where(fn ($q) => $q->where('product_id', $product->id)->where('color_id', $request->input('color_id')))->ignore($variant?->id)], 'color_id' => 'required|exists:colors,id', 'price' => 'required|integer|min:0|max:1000000000', 'low_stock_threshold' => 'required|integer|min:0|max:1000000', 'is_active' => 'required|boolean']);
        if ($variant) {
            $variant->update($data);
        } else {
            $product->variants()->create($data);
        }

        return back()->with('success', 'Đã lưu biến thể. Tồn kho chỉ thay đổi qua phiếu nhập và bán hàng.');
    }

    public function image(Request $request, Product $product, ProductImage $image)
    {
        abort_unless($image->product_id === $product->id, 404);
        $request->validate(['action' => 'required|in:cover,delete']);
        DB::transaction(function () use ($request, $product, $image) {
            Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            if ($request->input('action') === 'cover') {
                $product->images()->increment('sort_order');
                $image->update(['sort_order' => 0]);
            } else {
                $path = $image->path;
                $image->delete();
                DB::afterCommit(fn () => Storage::disk('public')->delete($path));
            }
        }, 3);

        return back()->with('success', 'Đã cập nhật thư viện ảnh.');
    }
}
