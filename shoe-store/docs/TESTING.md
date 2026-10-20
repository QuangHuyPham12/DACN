# Kiểm thử SOLE Footwear

## Môi trường

PHP 8.3.35, Laravel 12, MySQL 8.0.46. Cùng bộ PHPUnit chạy trên SQLite trong bộ nhớ và MySQL riêng sole_store_test. Website dùng sole_store, tách khỏi database test.

~~~powershell
php artisan test
php vendor/phpunit/phpunit/phpunit --configuration phpunit.mysql.xml
~~~

Trên máy hiện tại thay php bằng ..\.tools\php\php.exe. Dùng trực tiếp PHPUnit với cấu hình MySQL để tránh Laravel truyền hai lần tham số --configuration. Không chạy nhiều bộ test MySQL cùng lúc. Database test có thể được migrate:fresh; tuyệt đối không đổi cấu hình test sang database chứa dữ liệu thật.

## Tình huống tự động

StoreFlowTest, ExtendedStoreFlowTest, DemoDataTest, CustomerShoppingTest và QrPaymentTest kiểm tra:

- Trang công khai/quản trị, login/logout/đổi mật khẩu, giới hạn truy cập.
- Nhân viên không gọi endpoint admin; tài khoản bị khóa không dùng phiên cũ.
- Tải ảnh hợp lệ, phục vụ ảnh, chặn SVG tải lên.
- SKU/tổ hợp size-màu trùng, nhập sai số lượng, chống ghi stock từ form sản phẩm.
- Nhập hàng xác nhận một lần, đơn nháp chưa tăng/giảm kho.
- Giá lấy từ server, snapshot lịch sử không bị sửa theo sản phẩm.
- Cạnh tranh đôi cuối, rollback nhiều dòng, không hủy đơn đã hoàn tất.
- Checkout token gắn session, gửi lặp không tạo đơn khác.
- Đăng ký/đăng nhập khách với guard riêng, mật khẩu băm, hồ sơ và đổi mật khẩu.
- Khách không vào admin, không xem/hủy đơn thuộc tài khoản khác.
- Yêu thích riêng, thêm lặp chỉ một dòng, bỏ yêu thích.
- Checkout chỉ gắn tài khoản đã xác thực, bỏ qua customer_id/tổng/giảm/phí do client giả.
- Mã cố định/phần trăm, trần giảm, điều kiện/hết hạn/hết lượt; lỗi rollback cả hồ sơ và đơn.
- Gửi lặp token không dùng thêm lượt, hủy đơn trả lại lượt đúng một lần.
- Miễn phí giao theo tiền hàng trước giảm; đơn cũ không bị tính lại khi sửa mã.
- Giữ hàng khi xác nhận, POS không dùng phần đã giữ, hủy giải phóng hàng.
- Tiến trình giao tuần tự; chỉ ghi tiền COD sau đã giao; hoàn tất lặp không trừ/thu thêm.
- Giao thất bại giữ hàng, chỉ giải phóng sau nhận đủ hàng hoàn về.
- Điều chỉnh có chứng từ và token, không ăn vào lượng đã giữ.
- Trả toàn bộ đơn chỉ một lần, hoàn tiền hàng sau giảm, không gồm phí giao; hàng hỏng không nhập lại.
- Hoàn tiền kỳ này từ đơn kỳ trước làm thực thu kỳ này âm và không sửa doanh số kỳ trước.
- Trang ưu đãi/điều chỉnh/trả/báo cáo và CSV, chặn công thức nguy hiểm trong ô CSV.
- Bộ lọc size/giá/tồn áp vào cùng một SKU, khoảng giá tùy chọn hợp lệ.
- Cài mới dữ liệu demo đủ 24 mẫu/72 SKU, sổ kho khớp, đơn online đã hoàn tất ở trạng thái đã giao; seed lại không nhân bản dữ liệu hay ghi đè tài khoản đã chỉnh sửa.

Kết quả lần kiểm tra bàn giao ngày 21/10/2026: **81 tests, 779 assertions, đạt toàn bộ trên cả SQLite và MySQL 8.0.46**. PHP liên quan được định dạng bằng Pint; Blade đã biên dịch thành công; JavaScript kiểm tra bằng node --check. Không dùng số lượng bài test như cam kết không còn lỗi.

CustomerShoppingTest bổ sung kiểm tra điều hướng đăng nhập/sản phẩm/thương hiệu, giá sắp xếp theo SKU khớp size/màu, quay về giỏ và giữ giỏ sau đăng nhập, chặn chuyển hướng ra ngoài. Chat kiểm tra hãng/size/màu/giá trên cùng SKU, trừ giữ chỗ, hết hàng/ngừng bán, ghi nhớ/reset tiêu chí, các dạng 800k/1tr5/1,5 triệu, chính sách, lịch sử 12 lượt, giới hạn request, không lộ khóa và chuyển về danh mục khi API lỗi. OpenAI dùng HTTP fake; chưa kiểm tra gọi thật vì chưa có API key.

## Kiểm tra đồng thời bằng hai process

~~~powershell
php scripts/check_concurrency.php
php scripts/check_concurrency.php --same-order
php scripts/check_concurrency.php --mysql
php scripts/check_concurrency.php --mysql --same-order
~~~

SQLite dùng file tạm riêng. MySQL dùng sole_store_test và dựng lại các bảng test. Tình huống đôi cuối: completed + rejected, stock=0, payments=1. Cùng đơn: completed + completed, stock=0, payments=1. Hai worker được đồng bộ bắt đầu bằng file cờ và chạy PHP độc lập, không chỉ gọi hai lần liên tiếp trong một process.

## Đối chiếu và import

~~~powershell
php scripts/verify_data.php
php scripts/export_mysql.php --with-data
php scripts/check_mysql_import.php
~~~

verify_data chỉ đọc database website. Import test dùng sole_store_test và kiểm tra cả schema-only lẫn demo; so số sản phẩm/đơn với nguồn và migration history. Các lệnh đã chạy thành công trên MySQL 8.0.46. Bản dữ liệu bàn giao có 24 sản phẩm, 72 SKU, 5 phiếu nhập, 34 đơn bán, 30 thanh toán và một phiếu trả demo; kết quả đối chiếu errors rỗng.

## Kiểm tra trình duyệt

- Trang chủ, bộ lọc, sản phẩm, bảng size, chọn variant và giỏ.
- Áp SOLE10: đơn 1.700.000đ giảm trần 150.000đ, tổng 1.550.000đ, giao miễn phí.
- Đăng nhập khách demo, thông tin được điền sẵn, đặt COD và thấy lịch sử.
- Đơn DH000032 được xử lý xác nhận/giữ hàng, chuẩn bị, ghi vận đơn, giao, hoàn tất và trả hàng demo.
- Màn hình điện thoại 390×844 và desktop; kiểm tra menu, không tràn chiều ngang, văn bản và nút.
- Ảnh chụp được lưu trong docs/screenshots.
- Bản cập nhật đã kiểm tra hỗ trợ đổi/trả, đánh giá được duyệt và khôi phục tài khoản trong khung nhúng rộng 390px, cao 844px; không tràn ngang. Ảnh `customer-care-responsive.png` ghi lại ba trang sau khi sửa bố cục điểm đánh giá. Không thay cho thử trên thiết bị thật.
- Chat hỏi “Nike size 40 dưới 1 triệu”, hỏi tiếp size 41, mở đúng SKU. Kiểm tra cả desktop và khung nhúng chiều rộng 390px/cao 844px để xác minh bố cục và thao tác chat; không thay cho kiểm tra bàn phím ảo trên điện thoại thật.

Giới hạn: không kiểm thử chuyển tiền/gateway thực, API vận chuyển, email/SMS hoặc tải lớn nhiều người dùng vì chưa tích hợp những dịch vụ đó. In/PDF dùng hộp in trình duyệt, không phải hóa đơn điện tử có ký số.

## Thanh toán QR và giao hàng bổ sung

QrPaymentTest có 12 ca/119 assertions: dữ liệu thanh toán phía server, cấu hình/snapshot ngân hàng, CRC/TLV, thông báo chuyển khoản không tự ghi tiền, link riêng/no-store, phân quyền và đúng số tiền, mã giao dịch duy nhất, giữ hàng trước trả tiền, không giao QR chưa đối soát, hoàn tất không thu hai lần, hủy/hoàn đầy đủ, tiền đến muộn và nhận hàng giao thất bại. Cập nhật điểm xử lý/vận đơn được ghi nhật ký và thoát HTML.

Đã kiểm tra hai process với `--qr-verify`, cả khác đơn/cùng đơn trên SQLite và MySQL: chỉ một payment. Ảnh QR tạo trên trình duyệt đã giải mã bằng jsQR, nội dung khớp payload do PHP tạo. Đây là tài khoản test, không chuyển tiền; trang HTML kiểm thử tạm được xóa sau kiểm tra.

Đơn demo DH000033 thử luồng nhập địa chỉ → trang thanh toán COD → theo dõi. Nhân viên cập nhật tuần tự tới đang giao, nhập hãng/mã vận đơn/điểm xử lý; khách tải lại thấy đúng thông tin. QR và theo dõi kiểm tra responsive 390px, không tràn ngang; chưa kiểm thử app ngân hàng thật.

Xem cấu hình và giới hạn tại [PAYMENT_DELIVERY.md](PAYMENT_DELIVERY.md).

## Hoàn thiện khách hàng ngày 21/10/2026

Bộ đầy đủ: **81 tests / 779 assertions**, đạt trên SQLite và MySQL 8.0.46. CustomerExperienceTest thêm 12 ca / 113 assertions: hết hạn QR và ngoại lệ đối soát; trả lượt coupon đúng một lần; tiền đến muộn; outbox email và lỗi SMTP; token reset riêng, hết hạn, dùng một lần và thu hồi phiên; đánh giá đã mua/duyệt/escape, chặn duyệt nội dung vừa bị sửa; yêu cầu đổi trả riêng tư/gửi lặp/điều kiện đóng; gallery giữ ảnh cũ/đổi bìa/chặn ảnh sản phẩm khác, giới hạn ảnh và xóa tệp vừa lưu khi transaction lỗi. Dữ liệu demo hiện 24 sản phẩm, 72 SKU, 34 đơn và 30 thanh toán. Đơn 34, đánh giá và yêu cầu hỗ trợ đều có nhãn DEMO, không có giao dịch thật. SMTP chưa cấu hình, không gửi thư thật.
