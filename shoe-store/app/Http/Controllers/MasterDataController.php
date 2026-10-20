<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Customer;
use App\Models\Size;
use App\Models\Supplier;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MasterDataController extends Controller
{
    private const TYPES = ['categories' => [Category::class, 'Loại giày'], 'brands' => [Brand::class, 'Thương hiệu'], 'sizes' => [Size::class, 'Kích cỡ'], 'colors' => [Color::class, 'Màu sắc'], 'suppliers' => [Supplier::class, 'Nhà cung cấp'], 'customers' => [Customer::class, 'Khách hàng']];

    private function meta(Request $request, string $resource): array
    {
        abort_unless(isset(self::TYPES[$resource]), 404);
        abort_unless($resource === 'customers' || $request->user()->role === 'admin', 403);

        return self::TYPES[$resource];
    }

    public function index(Request $request, string $resource)
    {
        [$class, $title] = $this->meta($request, $resource);
        $request->validate(['q' => 'nullable|string|max:200', 'edit' => 'nullable|integer']);
        $query = $class::query();
        if ($q = $request->query('q')) {
            $query->where(function ($b) use ($q, $resource) {
                $b->where('name', 'like', '%'.$q.'%');
                if (in_array($resource, ['customers', 'suppliers'])) {
                    $b->orWhere('phone', 'like', '%'.$q.'%');
                }
            });
        }
        $records = $query->latest('id')->paginate(12)->withQueryString();
        $edit = $request->query('edit') ? $class::findOrFail($request->query('edit')) : null;

        return view('admin.master', ['pageTitle' => $title, 'resource' => $resource, 'records' => $records, 'edit' => $edit, 'contact' => in_array($resource, ['customers', 'suppliers'])]);
    }

    public function save(Request $request, string $resource, ?int $record = null)
    {
        [$class] = $this->meta($request, $resource);
        $model = $record ? $class::findOrFail($record) : new $class;
        $rules = ['name' => ['required', 'string', 'max:100']];
        if (in_array($resource, ['customers', 'suppliers'])) {
            $rules += ['phone' => 'nullable|string|max:30', 'email' => 'nullable|email|max:255', 'address' => 'nullable|string|max:255'];
        } else {
            $rules['name'][] = Rule::unique($resource)->ignore($model->id);
        }
        $model->fill($request->validate($rules))->save();

        return redirect()->route('data.index', $resource)->with('success', 'Đã lưu thông tin.');
    }

    public function destroy(Request $request, string $resource, int $record)
    {
        [$class] = $this->meta($request, $resource);
        $model = $class::findOrFail($record);
        $relation = match ($resource) {
            'categories', 'brands' => 'products', 'sizes', 'colors' => 'variants', 'suppliers' => 'purchases', 'customers' => 'orders'
        };
        if ($model->$relation()->exists()) {
            return back()->withErrors(['record' => 'Dữ liệu đã được sử dụng. Không thể xóa để bảo toàn lịch sử.']);
        }
        try {
            $model->delete();
        } catch (QueryException $e) {
            return back()->withErrors(['record' => 'Dữ liệu đang được sử dụng và không thể xóa.']);
        }

        return back()->with('success', 'Đã xóa dữ liệu.');
    }

    public function customer(Customer $customer)
    {
        return view('admin.customer', ['pageTitle' => $customer->name, 'customer' => $customer, 'orders' => $customer->orders()->latest()->paginate(15)]);
    }
}
