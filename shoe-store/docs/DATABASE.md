# Thiết kế cơ sở dữ liệu SOLE

Database chính: **MySQL 8.0**, charset utf8mb4. Bản bàn giao được chạy và kiểm tra trên **MySQL 8.0.46**. SQLite phục vụ kiểm thử độc lập và giữ bản nguồn trước nâng cấp.

Hệ thống có **25 bảng nghiệp vụ** tính cả users, cùng 9 bảng hạ tầng Laravel, tổng cộng 34 bảng. Migration là nguồn thiết kế; bản SQL được biên dịch từ migration và đã thử import. Tiền lưu bằng DECIMAL(15,0), xử lý bằng số nguyên VND trong PHP.

## Danh mục bảng

| Bảng | Nội dung | Ràng buộc chính |
|---|---|---|
| users | Nhân viên, quản trị viên | Email duy nhất, role admin/staff, is_active |
| categories | Loại giày | Tên duy nhất |
| brands | Thương hiệu | Tên duy nhất |
| sizes | Kích cỡ | Tên duy nhất |
| colors | Màu sắc | Tên duy nhất |
| products | Mẫu giày | FK loại/thương hiệu, trạng thái kinh doanh |
| product_images | Ảnh sản phẩm | FK sản phẩm, đường dẫn nội bộ |
| product_variants | SKU theo size/màu | SKU và bộ product-size-color duy nhất; price, stock, reserved_stock |
| suppliers | Nhà cung cấp | Thông tin liên hệ |
| customers | Hồ sơ khách hàng | Khách lẻ có thể không có hồ sơ |
| customer_accounts | Tài khoản khách | Email duy nhất, mỗi hồ sơ một tài khoản, mật khẩu băm |
| wishlists | Sản phẩm yêu thích | Cặp tài khoản-sản phẩm duy nhất |
| coupons | Mã ưu đãi | Code duy nhất, điều kiện, thời hạn, trần và bộ đếm |
| purchase_orders | Phiếu nhập | FK nhà cung cấp/nhân viên; draft/confirmed |
| purchase_order_items | Chi tiết nhập | Mỗi SKU một dòng; số lượng và giá nhập |
| sales_orders | Đơn POS/online | Khách, tiền hàng/giảm/phí/tổng, thanh toán, tiến trình giao, token |
| sales_order_items | Chi tiết bán | Mỗi SKU một dòng; snapshot tên/SKU/size/màu/giá |
| payments | Tiền đã thu | Một khoản thu/đơn, mã ngân hàng duy nhất, người đối soát, thời điểm thu; hoàn đơn hủy có thời điểm/mã/người xác nhận |
| order_events | Lịch sử xử lý | FK đơn, nhân viên tùy chọn, trạng thái và mô tả |
| inventory_movements | Sổ biến động | Tăng/giảm, tồn sau, người thực hiện và tham chiếu chứng từ |
| inventory_adjustments | Điều chỉnh kho | SKU, chênh lệch, trước/sau, lý do, token duy nhất |
| sales_returns | Trả toàn bộ đơn | Một phiếu trên mỗi đơn, tiền hoàn, nhập lại hay không, lý do |

Bảng bổ sung: product_reviews (đánh giá đã mua), support_requests (yêu cầu đổi/trả), order_messages (hàng đợi email xác nhận). Mỗi đánh giá duy nhất theo tài khoản/sản phẩm; mỗi đơn một yêu cầu hỗ trợ và một email theo loại.

Các bảng hạ tầng: migrations, password_reset_tokens, customer_password_reset_tokens, sessions, cache, cache_locks, jobs, job_batches, failed_jobs. Token khách tách riêng token nhân viên.

Migration 000004 còn bổ sung customer_email, payment_expires_at, expired_at trên đơn, auth_version trên tài khoản khách, sort_order trên ảnh và material/fit_note/care_note/size_note trên sản phẩm. Database có 34 bảng, trong đó 25 nghiệp vụ và 9 hạ tầng.

## ERD

~~~mermaid
erDiagram
    CATEGORIES ||--o{ PRODUCTS : classifies
    BRANDS ||--o{ PRODUCTS : owns
    PRODUCTS ||--o{ PRODUCT_IMAGES : has
    PRODUCTS ||--o{ PRODUCT_VARIANTS : has
    SIZES ||--o{ PRODUCT_VARIANTS : sizes
    COLORS ||--o{ PRODUCT_VARIANTS : colors
    SUPPLIERS ||--o{ PURCHASE_ORDERS : supplies
    USERS ||--o{ PURCHASE_ORDERS : creates
    PURCHASE_ORDERS ||--|{ PURCHASE_ORDER_ITEMS : contains
    PRODUCT_VARIANTS ||--o{ PURCHASE_ORDER_ITEMS : receives
    CUSTOMERS ||--o| CUSTOMER_ACCOUNTS : authenticates
    CUSTOMER_ACCOUNTS ||--o{ WISHLISTS : saves
    PRODUCTS ||--o{ WISHLISTS : appears_in
    CUSTOMERS o|--o{ SALES_ORDERS : places
    USERS o|--o{ SALES_ORDERS : handles
    COUPONS o|--o{ SALES_ORDERS : discounts
    SALES_ORDERS ||--|{ SALES_ORDER_ITEMS : contains
    PRODUCT_VARIANTS ||--o{ SALES_ORDER_ITEMS : sells
    SALES_ORDERS ||--o| PAYMENTS : receives
    SALES_ORDERS ||--o{ ORDER_EVENTS : logs
    SALES_ORDERS ||--o| SALES_RETURNS : returns
    USERS ||--o{ SALES_RETURNS : confirms
    PRODUCT_VARIANTS ||--o{ INVENTORY_MOVEMENTS : records
    PRODUCT_VARIANTS ||--o{ INVENTORY_ADJUSTMENTS : adjusts
    USERS ||--o{ INVENTORY_ADJUSTMENTS : performs
    USERS o|--o{ INVENTORY_MOVEMENTS : performs
~~~

## Trạng thái và công thức

- Phiếu nhập: draft → confirmed. Chỉ xác nhận mới tăng stock và ghi movement.
- Đơn bán: draft (POS), pending (online), completed, cancelled.
- Giao hàng: pending → confirmed → packing → shipping → delivered.
- Giao thất bại: shipping → delivery_failed → shipping (thử lại), hoặc returned_to_store (đã nhận đủ hàng về, hủy đơn).
- stock là tồn sổ sách chưa xuất bán, bao gồm phần giữ chỗ và đang giao, kể cả đã trả trước.
- available_stock = stock − reserved_stock; không lưu trùng cột này.
- COD xác nhận mới tăng reserved_stock; QR tăng ngay khi tạo đơn, không tăng lần nữa khi xác nhận. Hủy trước giao/nhận hàng giao thất bại về giảm phần giữ chỗ.
- Hoàn tất giảm stock và phần giữ chỗ của chính đơn, ghi movement và payment nếu chưa có. QR đã được ghi payment khi nhân viên đối soát đủ tiền trước giao; hoàn tất không tạo thêm payment.
- subtotal = tổng quantity × unit_price; total = subtotal − discount + shipping_fee.
- discount cố định hoặc phần trăm làm tròn xuống VND; chặn bởi tiền hàng và maximum_discount.
- Phí giao tính từ tiền hàng trước giảm, cấu hình tại config/store.php.
- used_count tính số đơn chưa hủy có dùng mã. Trả hàng sau thanh toán vẫn tính đã dùng.
- Đơn QR đã hủy vẫn giữ payment, chờ hoàn toàn bộ total; refunded_at/refund_reference/refunded_by chỉ ghi sau hoàn tiền thực tế. Tiền đến muộn được đối soát trên đơn hủy để hoàn.
- refund_amount = subtotal − discount. Hoàn toàn bộ tiền hàng, không hoàn phí giao.
- Đơn gốc và payment giữ nguyên sau trả; sales_returns là chứng từ bù. Nếu restock=true, movement loại return tăng lại từng SKU.
- Điều chỉnh có số chênh lệch khác 0; tồn sau không âm, không thấp hơn giữ chỗ, không vượt 2 tỷ đôi.

## Nhất quán và xử lý đồng thời

StoreService xử lý mọi nghiệp vụ kho/đơn/tiền bằng transaction, khóa đơn và SKU; khóa SKU theo thứ tự ID. Một dòng không hợp lệ sẽ rollback toàn bộ. Số lượng xuất có kiểm tra điều kiện và không lấy tồn/giá từ client.

Thanh toán, trả đơn và movement có khóa duy nhất để bổ sung chống ghi lặp. Token checkout và điều chỉnh là duy nhất. Với MySQL, truy vấn FOR UPDATE đọc trạng thái hiện tại khi giữ khóa; giao dịch có tối đa ba lần thử khi Laravel nhận diện deadlock. Với SQLite, lấy quyền ghi trước khi đọc để tránh nâng khóa không an toàn.

Lịch sử tên/SKU/size/màu/giá nằm trong chi tiết giao dịch. Sửa danh mục không làm viết lại chứng từ cũ. Xóa hồ sơ đã dùng bị ràng buộc FK chặn. Ngừng kinh doanh chỉ ảnh hưởng giao dịch mới; đơn online đã giữ hàng vẫn được hoàn tất theo cam kết trước đó.

Hai guard web và customer tách tài khoản nhân viên/khách. Checkout chỉ gắn customer_id từ tài khoản phía server; khách chưa đăng nhập được tạo hồ sơ mới, không gộp theo số điện thoại. Trang theo dõi dùng token ngẫu nhiên 48 ký tự và header private/no-store. Ai có link đều có thể xem thông tin nhận hàng nên cần giữ link riêng.

inventory_movements.reference_type/reference_id là tham chiếu logic đến phiếu purchase/sale/return/adjustment, không phải FK đa hình. Các FK thông thường được ràng buộc trong MySQL. Tồn/giữ chỗ/điều kiện ưu đãi còn được kiểm tra bởi nghiệp vụ PHP; không tuyên bố mọi quy tắc nằm ở CHECK constraint database.

## Migration và bàn giao SQL

Migration nền: 2026_10_21_000001_create_store_tables.php. Migration mở rộng: 2026_10_21_000002_extend_store_workflows.php. Migration QR/vận chuyển: 2026_10_21_000003_add_qr_payments.php, thêm bank_snapshot JSON, payment_method, transfer_reported_at, shipping_carrier, delivery_location và các cột đối soát/hoàn của payments. Không thêm bảng. Nâng cấp giữ nguyên dữ liệu cũ, điền subtotal=total cho đơn lịch sử và đánh dấu đã giao cho đơn hoàn tất.

- sole_store_schema.sql: cấu trúc + migration history, chưa có dữ liệu nghiệp vụ.
- sole_store_demo.sql: cấu trúc + dữ liệu demo + migration history.
- Import một file vào database trống. SQL không DROP database/bảng cũ và không chứa APP_KEY hay mật khẩu kết nối.
- scripts/export_mysql.php --with-data xuất dữ liệu hiện tại, kể cả hash mật khẩu; không dùng bản dữ liệu thật để chia sẻ công khai.
- scripts/check_mysql_import.php kiểm tra import trên sole_store_test, dựng lại bảng của database test.

Chạy scripts/verify_data.php để đối chiếu tổng movement với stock, giữ chỗ với đơn pending, tiền hàng/tổng/payment, số lượt mã và tiền hoàn. errors rỗng nghĩa là các phép đối chiếu này khớp; không thay thế sao lưu hoặc kiểm thử.

Chi tiết trạng thái tiền độc lập với giao hàng, cách bật QR và công thức dòng tiền: [PAYMENT_DELIVERY.md](PAYMENT_DELIVERY.md).
