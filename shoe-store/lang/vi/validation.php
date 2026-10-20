<?php

return [
    'required' => 'Vui lòng nhập :attribute.', 'email' => ':attribute phải là email hợp lệ.',
    'integer' => ':attribute phải là số nguyên.', 'numeric' => ':attribute phải là số.',
    'string' => ':attribute phải là chuỗi ký tự.', 'unique' => ':attribute đã tồn tại.',
    'exists' => ':attribute được chọn không tồn tại.', 'in' => ':attribute không hợp lệ.',
    'boolean' => ':attribute không hợp lệ.', 'confirmed' => 'Xác nhận :attribute không khớp.',
    'current_password' => 'Mật khẩu hiện tại không đúng.', 'regex' => ':attribute không đúng định dạng.',
    'distinct' => 'Không được chọn trùng :attribute.', 'array' => ':attribute phải là danh sách.',
    'uuid' => ':attribute không hợp lệ. Vui lòng tải lại trang.', 'image' => ':attribute phải là ảnh.',
    'mimes' => ':attribute phải thuộc các định dạng: :values.',
    'date_format' => ':attribute phải theo định dạng :format.', 'after_or_equal' => ':attribute phải cùng hoặc sau :date.',
    'min' => ['numeric' => ':attribute phải từ :min trở lên.', 'string' => ':attribute phải có ít nhất :min ký tự.', 'array' => ':attribute phải có ít nhất :min phần tử.'],
    'max' => ['numeric' => ':attribute không được vượt quá :max.', 'string' => ':attribute không quá :max ký tự.', 'array' => ':attribute không quá :max phần tử.', 'file' => ':attribute không quá :max KB.'],
    'password' => ['letters' => 'Mật khẩu phải có chữ cái.', 'numbers' => 'Mật khẩu phải có chữ số.'],
    'attributes' => ['email' => 'email', 'password' => 'mật khẩu', 'name' => 'tên', 'items' => 'dòng hàng', 'items.*.variant_id' => 'biến thể SKU', 'items.*.quantity' => 'số lượng', 'items.*.unit_cost' => 'giá nhập', 'variant_id' => 'biến thể', 'quantity' => 'số lượng', 'customer_name' => 'họ tên', 'customer_phone' => 'số điện thoại', 'shipping_address' => 'địa chỉ nhận hàng', 'supplier_id' => 'nhà cung cấp', 'price' => 'giá bán', 'sku' => 'SKU', 'size_id' => 'kích cỡ', 'color_id' => 'màu sắc', 'category_id' => 'loại giày', 'brand_id' => 'thương hiệu', 'image' => 'ảnh sản phẩm', 'from' => 'ngày bắt đầu', 'to' => 'ngày kết thúc', 'note' => 'ghi chú', 'role' => 'vai trò', 'current_password' => 'mật khẩu hiện tại'],
];
