# SOLE Footwear — Website quản lý cửa hàng bán giày

**Đề tài:** Xây dựng website quản lý cửa hàng bán giày bằng **PHP, JavaScript và MySQL**.

Nhóm 6: **Phạm Quang Huy — Đào Ngọc Tú**. Ứng dụng PHP 8.2+, Laravel 12, Blade, JavaScript thuần và MySQL 8; giao diện tiếng Việt, hỗ trợ bán tại quầy và mua sắm trực tuyến. HTML/CSS và tài nguyên được phục vụ tại máy, không cần npm để chạy giao diện.

## Mở ngay trên máy hiện tại

Trong PowerShell tại thư mục `shoe-store`:

~~~powershell
.\start.ps1
~~~

Script tự khởi động phiên MySQL riêng của đồ án nếu cần, chạy migration/seed an toàn rồi mở server phát triển.

- Cửa hàng: [http://127.0.0.1:8000](http://127.0.0.1:8000)
- Quản trị: [http://127.0.0.1:8000/admin](http://127.0.0.1:8000/admin)
- Tài khoản khách: [http://127.0.0.1:8000/tai-khoan](http://127.0.0.1:8000/tai-khoan)

**Bản chạy hiện tại đã dùng MySQL 8.0.46** tại `127.0.0.1:3309`, database `sole_store`. Phiên MySQL này sử dụng dữ liệu riêng trong `../.tools/mysql-local/data`; không thay đổi dịch vụ hay database MySQL có sẵn. Mật khẩu kết nối đã được tạo ngẫu nhiên và lưu trong `.env`, không ghi vào tài liệu. Database SQLite cũ còn được giữ làm bản nguồn dự phòng; nó không còn là database của website đang chạy.

Nếu website đã chạy tại cổng 8000, mở URL trực tiếp. MySQL và PHP cần được khởi động lại sau khi tắt máy.

## Tài khoản demo

| Vai trò | Email | Mật khẩu |
|---|---|---|
| Quản trị viên | admin@sole.vn | Demo@12345 |
| Nhân viên | staff@sole.vn | Demo@12345 |
| Khách hàng | khach@sole.vn | Demo@12345 |

Các tài khoản và giao dịch là dữ liệu giả lập cho đồ án. Mã `SOLE10`: giảm 10%, tối đa 150.000đ, đơn từ 500.000đ. Mã `WELCOME50`: giảm 50.000đ, đơn từ 500.000đ. Mỗi mã có 1.000 lượt và có thể được quản trị viên sửa/tắt.

Dữ liệu nền: 24 mẫu giày, 72 biến thể, 6 thương hiệu, 4 loại giày, 4 nhà cung cấp, 5 phiếu nhập và đơn bán trên nhiều ngày. Các lần kiểm tra trình duyệt có thể bổ sung đơn demo. Hình mặc định là SVG minh họa của dự án, không phải ảnh sản phẩm chính hãng; quản trị viên có thể tải ảnh JPG/PNG/WebP.

## Bản cập nhật trải nghiệm khách hàng

Đã thêm hết hạn QR tự động, khôi phục mật khẩu và thu hồi phiên cũ, email xác nhận có hàng đợi, đánh giá sau mua có kiểm duyệt, yêu cầu đổi/trả và thư viện tối đa 8 ảnh. Chạy `start.ps1` để bật cả website và tác vụ nền. Email hiện ở chế độ log, cần SMTP để gửi thật. Xem [CUSTOMER_EXPERIENCE.md](docs/CUSTOMER_EXPERIENCE.md).

Báo cáo Word/PDF 13 trang và slide bảo vệ 12 trang nằm trong [hồ sơ bảo vệ](docs/defense/README.md).

## Chức năng

### Khách hàng

- Trang chủ với bộ sưu tập, ưu đãi đang có, sản phẩm và nội dung hướng dẫn.
- Tìm/lọc tên, loại giày, thương hiệu, size, khoảng giá, hàng còn có thể bán; sắp xếp và phân trang.
- Chi tiết sản phẩm, gallery ảnh, chọn nhanh size/màu, lượng hàng có thể bán, hướng dẫn đo chân, sản phẩm liên quan.
- Giỏ hàng: cập nhật/xóa, áp mã ưu đãi, tính phí giao và hiển thị tổng tiền trước khi đặt.
- Đặt COD hoặc chuyển khoản VietQR có/không có tài khoản. Sau nhập địa chỉ có bước thanh toán riêng: QR đúng số tiền/mã đơn, tải QR, sao chép nội dung và báo đã chuyển; cửa hàng đối soát tiền nhận thực tế.
- Theo dõi bước giao, mã vận đơn, đơn vị giao, vị trí xử lý mới nhất và nhật ký do cửa hàng cập nhật.
- Đăng ký/đăng nhập/đăng xuất, sửa thông tin nhận hàng, đổi mật khẩu, lịch sử đơn riêng.
- Yêu thích sản phẩm; tự hủy đơn của mình trước khi cửa hàng xác nhận.
- Trang thương hiệu riêng, số mẫu và giá khởi điểm theo từng hãng; trang sản phẩm riêng với lọc màu và khoảng giá nhanh. Giá hiển thị/sắp xếp dựa trên SKU khớp bộ lọc.
- SOLE Assistant: chat tra cứu giày theo hãng, size, màu và ngân sách; ghi nhớ tiêu chí hỏi tiếp, thẻ mua đúng SKU, tư vấn giao hàng/ưu đãi/size/đổi trả. Bản hiện tại chạy không cần API và ghi rõ chưa kết nối AI. Đã có kết nối OpenAI tùy chọn phía server; xem [cấu hình và phạm vi chatbot](docs/ADVISOR.md).

### Nhân viên và quản trị

- Đăng nhập, chống đăng nhập sai liên tiếp, đổi mật khẩu, phân quyền và khóa tài khoản.
- Quản lý sản phẩm, ảnh, SKU, size/màu, giá bán, ngưỡng tồn và ngừng kinh doanh.
- Quản lý loại giày, thương hiệu, size, màu, khách hàng và nhà cung cấp.
- Phiếu nhập nháp/xác nhận, tăng kho một lần, bản in.
- POS nhiều sản phẩm, khách lẻ/khách có hồ sơ, mã ưu đãi, tiền mặt hoặc chuyển khoản đã nhận, phiếu bán.
- Quy trình online: xác nhận → chuẩn bị → đang giao → đã giao → hoàn tất. COD thu sau giao; QR đối soát trước bàn giao, không thu lại khi hoàn tất.
- Đối soát QR bằng mã giao dịch/số tiền; đơn hủy đã trả trước có theo dõi chờ hoàn/đã hoàn, quản trị xác nhận tiền hoàn thực tế.
- Giao chưa thành công: giao lại hoặc xác nhận đã nhận hàng hoàn về rồi hủy và giải phóng giữ chỗ.
- Tồn sổ sách, giữ chỗ, số có thể bán, cảnh báo tồn thấp, sổ kho theo SKU.
- Kiểm kê/điều chỉnh kho có lý do, người thực hiện và chứng từ; chống ghi lặp.
- Trả toàn bộ đơn một lần, ghi nhận hoàn tiền hàng, nhập lại kho nếu đủ điều kiện bán.
- Mã giảm cố định/phần trăm, trần giảm, đơn tối thiểu, thời hạn, giới hạn lượt.
- Dashboard, thực thu sau hoàn tiền, báo cáo theo khoảng ngày, sản phẩm bán chạy, CSV mở được trong Excel và in/lưu PDF.

Nhân viên chỉ tra cứu sản phẩm/kho, quản lý khách và xử lý đơn. Các chức năng sửa sản phẩm, nhập/điều chỉnh kho, trả hàng, ưu đãi, nhân viên và báo cáo/xuất dữ liệu yêu cầu quản trị viên. Tài khoản khách dùng guard riêng, không có quyền quản trị.

## Quy tắc quan trọng

- Tồn không sửa trực tiếp qua biểu mẫu sản phẩm. Số có thể bán = tồn sổ sách − lượng giữ chỗ.
- COD giữ hàng khi xác nhận; QR giữ ngay khi tạo đơn. Khóa SKU và kiểm tra số có thể bán trong transaction. Đơn QR mới tự hết hạn sau 30 phút nếu chưa báo chuyển, chưa thu và chưa được xác nhận; tác vụ nền trả phần giữ và lượt ưu đãi đúng một lần.
- Hàng giữ chỗ/đang giao vẫn nằm trong tồn sổ sách đến khi hoàn tất, kể cả QR đã thu trước. Chỉ hoàn tất khi đã giao và nhận tiền; xuất kho/giải phóng giữ chỗ một lần, dùng lại khoản thu QR đã đối soát.
- Không hủy trực tiếp hàng đang giao, giao thất bại hoặc đã giao. Hàng giao thất bại được giải phóng sau khi nhận đủ hàng hoàn về.
- Giá/giảm giá/phí giao do máy chủ tính. Sửa giá hoặc ưu đãi sau này không làm đổi chứng từ cũ.
- Phí giao mặc định 30.000đ, miễn phí từ 1.500.000đ tiền hàng **trước giảm giá**; chỉnh tại `config/store.php`.
- Mã được tính lượt ngay khi tạo đơn; hủy trả lại lượt, trả hàng sau thanh toán không trả lại lượt.
- Trả hàng hoàn toàn bộ tiền hàng sau giảm giá, không hoàn phí giao. Đây là ghi nhận nhân viên đã hoàn tiền thực tế, không tự chuyển khoản.
- Doanh thu dựa trên đơn hoàn tất, trừ tiền trả hàng theo ngày lập phiếu. Dòng tiền tách riêng theo ngày thực thu/hoàn; không tính trả trước thành doanh thu. Xem cả số dư chờ hoàn và tiền trả trước; chưa phải lợi nhuận.
- Giao dịch kho/tiền được khóa và xử lý nguyên tử; một dòng lỗi rollback toàn bộ. Token và khóa duy nhất ngăn gửi lặp.
- Không tự gộp tài khoản/đơn theo số điện thoại. Chỉ đơn đặt khi đăng nhập mới gắn vào lịch sử tài khoản.

## Bật thanh toán QR

Điền `PAYMENT_BANK_BIN`, `PAYMENT_BANK_NAME`, `PAYMENT_ACCOUNT_NUMBER`, `PAYMENT_ACCOUNT_NAME` trong `.env`, rồi chạy `php artisan config:clear`. Bản hiện tại để trống theo lựa chọn của bạn; QR chỉ được bật khi ngân hàng hợp lệ. Có hướng dẫn từng bước tại [PAYMENT_DELIVERY.md](docs/PAYMENT_DELIVERY.md).

QR được tạo ngay tại máy, không gọi dịch vụ tạo ảnh QR. Bấm “Tôi đã chuyển khoản” chỉ gửi thông báo; nhân viên kiểm tra sao kê trước khi xác nhận tiền. Vị trí giao hàng do cửa hàng cập nhật, chưa có GPS/API vận chuyển trực tiếp.

## Cài trên máy mới với MySQL

Cần PHP 8.2+ (khuyến nghị 8.3), Composer 2, MySQL 8.0+ và các extension `pdo_mysql`, `pdo_sqlite` (cho test), `mbstring`, `fileinfo`, `openssl`, `xml`, `dom`, `curl`, `zip`, `bcmath`.

~~~powershell
composer install
Copy-Item .env.mysql.example .env
php artisan key:generate
~~~

Tạo một database trống `sole_store`, charset `utf8mb4`. Điền DB_HOST/PORT/DATABASE/USERNAME/PASSWORD của máy đó vào `.env`, giữ nguyên APP_KEY nếu đã có dữ liệu mã hóa. Chọn **một** cách:

1. `php artisan config:clear` rồi `php artisan migrate --seed`.
2. Import `database/sql/sole_store_demo.sql` trong phpMyAdmin/MySQL, rồi `php artisan config:clear`. Đã có dữ liệu và lịch sử migration; không cần seed lại.
3. Import `database/sql/sole_store_schema.sql`, rồi `php artisan db:seed` nếu cần demo.

Sau đó `php artisan serve`. Không import hai file vào cùng database; file SQL không tự xóa hay tạo database. Hai file đã được kiểm tra import trên MySQL 8.0.46. Không sao chép trực tiếp datadir của MySQL giữa các máy; dùng SQL export/import.

SQLite chỉ dành cho thử nghiệm độc lập: dùng `.env.sqlite.example`, tạo `database/database.sqlite`, cấu hình DB_CONNECTION=sqlite và bỏ DB_DATABASE của MySQL trước khi migrate.

## Kiểm thử

Kết quả bàn giao: **81 ca kiểm thử, 779 assertions đạt trên cả SQLite và MySQL 8.0.46**; đã kiểm tra import hai bản SQL, đối chiếu dữ liệu và luồng đặt/giao/trả hàng trên trình duyệt. Phần khách hàng/chat được kiểm tra thêm ở chiều rộng 390px; kết nối OpenAI được kiểm thử giả lập, chưa gọi dịch vụ bằng API key thật.

~~~powershell
php artisan test
php vendor/phpunit/phpunit/phpunit --configuration phpunit.mysql.xml
php scripts/verify_data.php
php scripts/check_concurrency.php
php scripts/check_concurrency.php --same-order
php scripts/check_concurrency.php --mysql
php scripts/check_concurrency.php --mysql --same-order
~~~

Trên máy hiện tại thay `php` bằng `..\.tools\php\php.exe`. PHPUnit mặc định dùng SQLite trong bộ nhớ. Bộ MySQL và test đồng thời dùng riêng **sole_store_test**, có thể tạo/xóa bảng của database test, không dùng database website. Không chạy hai lệnh test MySQL cùng lúc. Tài khoản database cần được cấp quyền trên database test.

Xuất và kiểm tra file SQL:

~~~powershell
php scripts/export_mysql.php --with-data
php scripts/check_mysql_import.php
~~~

`--with-data` chứa dữ liệu nghiệp vụ và hash mật khẩu của cấu hình hiện tại. Lệnh kiểm tra import dựng lại **sole_store_test**; chỉ chạy trên môi trường phát triển. Không dùng bản có thông tin khách thật để chia sẻ công khai.

Chi tiết: [docs/TESTING.md](docs/TESTING.md). Kịch bản bảo vệ: [docs/DEMO.md](docs/DEMO.md).

## Tài liệu bàn giao

- [Thiết kế database và ERD](docs/DATABASE.md): 25 bảng nghiệp vụ và các ràng buộc.
- [Phân tích, kiến trúc và ma trận chức năng](docs/PROJECT.md).
- [Kịch bản demo và đối chiếu kết quả](docs/DEMO.md).
- [Các tình huống kiểm thử](docs/TESTING.md).
- [Cấu hình ngân hàng, thanh toán QR và theo dõi giao hàng](docs/PAYMENT_DELIVERY.md).
- [Hướng dẫn chat tư vấn và kết nối AI](docs/ADVISOR.md).
- [Ảnh giao diện](docs/screenshots/).

Mã nguồn nghiệp vụ tại `app/Services/StoreService.php`; controller tại `app/Http/Controllers`; giao diện tại `resources/views`; CSS/JS tại `public/assets`. Quyền truy cập được kiểm tra trên route/middleware và phạm vi tài khoản tại controller.

## Sao lưu và phạm vi triển khai

Sao lưu bằng MySQL dump hoặc Export phpMyAdmin, đồng thời sao lưu `storage/app/public/product-images` và `.env` riêng. Dữ liệu MySQL local nằm ngoài thư mục app tại `../.tools/mysql-local`; giữ thư mục này khi làm việc trên máy hiện tại. Mật khẩu quản trị phiên MySQL riêng nằm trong file bị Git bỏ qua tại đó.

Phạm vi: một cửa hàng, một kho; trả toàn bộ đơn, chưa trả từng dòng; chưa có giá vốn/lợi nhuận, webhook tự xác nhận ngân hàng/cổng thanh toán, API hãng vận chuyển, SMS, trả từng dòng hoặc giá vốn đầy đủ. Mã vận đơn và các giao dịch thu/hoàn tiền được nhân viên ghi nhận. Bảng size là tham khảo, cần đối chiếu mẫu thực tế.

Để đưa lên Internet: dùng web server trỏ vào `public`, HTTPS, APP_ENV=production, APP_DEBUG=false, cấu hình cookie an toàn, tài khoản database riêng, mật khẩu thật và backup. Thay ảnh minh họa, nội dung liên hệ/chính sách bằng thông tin cửa hàng đã xác nhận. `artisan serve` và tài khoản demo phục vụ phát triển/đồ án.
