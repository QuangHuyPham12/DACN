# Phân tích và thiết kế website SOLE

## Chương 1 — Bài toán và phạm vi

**Tên đề tài:** Xây dựng website quản lý cửa hàng bán giày bằng PHP, JavaScript và MySQL.

Cửa hàng cần quản lý sản phẩm theo từng size/màu, nhập hàng, tồn kho, bán tại quầy và đơn online. Tồn và doanh số phải đối chiếu được với chứng từ, tránh trừ kho hoặc thu tiền nhiều lần khi người dùng gửi lại thao tác.

Hệ thống phục vụ một cửa hàng, một kho. Ba nhóm người dùng: quản trị viên, nhân viên bán hàng và khách hàng. Khách chưa đăng nhập vẫn có thể xem và đặt COD hoặc QR khi đã cấu hình ngân hàng. Tài khoản khách giúp lưu thông tin, yêu thích và lịch sử đơn gắn với chính tài khoản đó.

### Ma trận quyền

| Nghiệp vụ | Khách | Nhân viên | Quản trị |
|---|---|---|---|
| Xem/lọc/chọn giày và đặt COD/QR | Có | Qua trang cửa hàng | Qua trang cửa hàng |
| Yêu thích, hồ sơ, lịch sử đơn riêng | Có khi đăng nhập khách | Tài khoản khách riêng | Tài khoản khách riêng |
| POS và xử lý giao hàng | Không | Có | Có |
| Tra cứu sản phẩm và tồn | Chỉ sản phẩm công khai | Có | Có |
| Quản lý khách hàng | Hồ sơ của mình | Có | Có |
| Sửa sản phẩm, danh mục, nhà cung cấp | Không | Không | Có |
| Nhập hàng, kiểm kê, điều chỉnh | Không | Không | Có |
| Trả hàng và ghi hoàn tiền | Không | Không | Có |
| Mã ưu đãi, tài khoản nhân viên | Không | Không | Có |
| Báo cáo, xuất dữ liệu | Không | Không | Có |

Nhân viên không được tự nâng quyền. Khách không được nhận quyền quản trị dù đã đăng nhập. Quản trị không được khóa hoặc tự hạ vai trò tài khoản đang dùng.

### Yêu cầu chất lượng

- Tiếng Việt, dùng được trên điện thoại và desktop, biểu mẫu có nhãn và thông báo lỗi.
- Giá/tồn/giảm phí được kiểm tra tại server. JavaScript phục vụ tương tác, không quyết định quyền hoặc số tiền.
- Các thay đổi kho/tiền nguyên tử, có chứng từ và chống ghi lặp.
- Không xóa dữ liệu đã có giao dịch. Thay đổi danh mục không sửa lịch sử.
- Có dữ liệu demo, SQL bàn giao, cách cài, tài khoản thử và kịch bản bảo vệ.

## Chương 2 — Phân tích và thiết kế

### Kiến trúc

~~~mermaid
flowchart LR
    Browser["HTML / CSS / JavaScript"] --> Routes["Laravel routes + CSRF + middleware"]
    Routes --> Controllers["Controllers: validate và giới hạn phạm vi"]
    Controllers --> Service["StoreService: transaction, khóa, nghiệp vụ"]
    Service --> Models["Eloquent models"]
    Models --> Database[("MySQL 8 / 25 bảng nghiệp vụ")]
    Controllers --> Views["Blade views tiếng Việt"]
    Views --> Browser
~~~

PHP 8.3 và Laravel 12 xử lý phía máy chủ. Blade sinh HTML; CSS/JavaScript nằm trong public/assets, chạy không cần CDN hoặc npm. MySQL 8 là database chính. Tài khoản nhân viên dùng guard web; tài khoản khách dùng guard customer.

StoreService tập trung nhập hàng, tạo/hoàn tất/hủy đơn, tiến trình giao, điều chỉnh và trả hàng. Controller kiểm tra yêu cầu và quyền trước khi gọi service. Ràng buộc unique/FK bổ sung lớp bảo vệ trong database.

ShoeAdvisor xử lý tư vấn công khai: tách tiêu chí tiếng Việt, truy vấn SKU còn có thể bán, giữ trạng thái hội thoại trong session và trả thẻ sản phẩm đúng lựa chọn. Chế độ mặc định dùng quy tắc/danh mục; kết nối OpenAI Responses là tùy chọn và chưa bật khi chưa có API key. Chat không thực hiện giao dịch hoặc đọc dữ liệu riêng. Chi tiết tại [ADVISOR.md](ADVISOR.md).

Chi tiết 22 bảng và ERD: [DATABASE.md](DATABASE.md).

### Các use case chính

**UC01 — Đặt hàng:** chọn SKU còn có thể bán → thêm giỏ → nhập địa chỉ → áp mã → server tính tổng → lưu pending cùng snapshot → nhận link ngẫu nhiên. Nếu thiếu hàng/mã sai, rollback toàn bộ.

**UC02 — Xử lý online:** khóa đơn và SKU → giữ hàng (COD khi xác nhận, QR khi tạo) → chuẩn bị → đối soát tiền QR trước giao → ghi vận đơn/điểm xử lý → giao thành công → thu COD nếu cần và xuất kho. QR đã thu được tái sử dụng, không thu hai lần. Không được nhảy bước hoặc lấy phần giữ chỗ của đơn khác.

**UC03 — Giao thất bại:** nhân viên ghi nhận chưa thành công; thử giao lại hoặc xác nhận đã nhận đủ hàng về. Chỉ sau nhận lại mới giải phóng giữ chỗ và hủy, tránh mở bán hàng đang trên đường.

**UC04 — Bán tại quầy:** tạo nháp bằng giá SKU phía server → kiểm tra và ghi nhận tiền → xuất kho, payment và trạng thái trong cùng transaction.

**UC05 — Nhập hàng:** lập nháp gồm SKU, số lượng, giá nhập → xác nhận nhận hàng → tăng kho và movement. Gửi lại cùng phiếu không tăng thêm.

**UC06 — Kiểm kê:** chọn SKU và phần chênh lệch, nhập lý do → kiểm tra tồn sau ≥ lượng đã giữ → lưu phiếu, cập nhật stock và movement. Token ngăn lặp.

**UC07 — Trả hàng:** chỉ đơn hoàn tất chưa có phiếu trả → xác nhận nhận toàn bộ hàng và hoàn tiền hàng → tùy chất lượng mà cộng lại tồn → giữ nguyên đơn/payment gốc, thêm phiếu bù.

**UC08 — Báo cáo:** chọn khoảng ngày → cộng tiền thu theo completed_at, trừ hoàn theo thời điểm lập phiếu trả → xem thực thu, doanh số, ưu đãi, vận chuyển và SKU bán chạy → in hoặc xuất CSV.

### Quy tắc tài chính và tồn

Tiền tệ VND, số nguyên. Tổng thanh toán = tiền hàng − giảm giá + giao hàng. Giảm phần trăm làm tròn xuống; không giảm quá tiền hàng hay trần chương trình. Phí giao 30.000đ, miễn từ 1.500.000đ tiền hàng trước ưu đãi; tham số nằm trong config/store.php.

Tồn sổ sách bao gồm lượng giữ chỗ và đang giao, kể cả đã trả trước. COD giữ khi xác nhận, QR giữ khi tạo đơn; chỉ hoàn tất mới xuất sổ sách. Có thể bán = tồn sổ sách − giữ chỗ. Sổ movement khớp stock; giữ chỗ được đối chiếu với chi tiết đơn pending.

Trả toàn bộ tiền hàng sau ưu đãi, không trả phí giao. Khoản hoàn thuộc kỳ lập phiếu trả, không sửa ngày hoàn tất đơn gốc. Thực thu âm là hợp lệ khi hoàn nhiều hơn thu trong kỳ; đây chưa phải lợi nhuận.

### Bảo vệ dữ liệu

Mật khẩu băm, CSRF, validation server, throttle đăng nhập, middleware vai trò, giới hạn dữ liệu theo chủ tài khoản, query binding qua Eloquent. Ảnh tải lên chỉ JPG/PNG/WebP. CSV tránh ô bị hiểu thành công thức. Link đơn có token ngẫu nhiên; trang đơn/tài khoản dùng private/no-store. Không tự gộp lịch sử theo số điện thoại.

## Chương 3 — Cài đặt và đánh giá

### Môi trường bàn giao

Website chạy MySQL 8.0.46 tại 127.0.0.1:3309, database sole_store, PHP 8.3.35. Instance database tách riêng trong .tools/mysql-local, tài khoản ứng dụng và mật khẩu ngẫu nhiên nằm trong .env. SQLite cũ được giữ nguyên.

Hai bản SQL gồm schema-only và demo, có migration history và đã được import thử vào sole_store_test. Dữ liệu mẫu có 24 mẫu giày, 72 biến thể, 4 nhà cung cấp, 5 phiếu nhập và các đơn qua nhiều ngày; kiểm tra trình duyệt bổ sung chứng từ demo.

### Giao diện

Cửa hàng dùng nền kem, chữ đậm, màu xanh olive và cam ấm; trang chủ có hero, ưu đãi, bộ lọc và bộ sưu tập. Chi tiết có lựa chọn size nhanh, hướng dẫn đo, gallery và sản phẩm liên quan. Giỏ tách rõ từng khoản tiền, thông tin nhận và COD/QR; trang thanh toán có VietQR nội bộ, tải mã và thông báo chuyển khoản. Trang theo dõi nổi bật chặng giao, đơn vị/mã vận đơn, vị trí và thời gian cập nhật. Trang tài khoản/timeline đơn đồng bộ.

Quản trị dùng thanh điều hướng xanh đậm, khối KPI, biểu đồ, bảng và các nút nghiệp vụ. Mobile menu, bảng có vùng cuộn, nhãn input, dialog xác nhận và trạng thái disabled giúp giảm thao tác sai.

### Đánh giá

Kiểm thử PHPUnit trên cả SQLite và MySQL; hai process PHP cạnh tranh tồn cuối và gửi lại cùng đơn; đối chiếu stock/reservations/tổng/payment/coupon/refund; import SQL; kiểm tra desktop và điện thoại. Chi tiết và kết quả bàn giao tại [TESTING.md](TESTING.md).

### Phạm vi mở rộng sau đồ án

Chưa tích hợp cổng thanh toán/ngân hàng, API vận chuyển, SMS, giá vốn/lợi nhuận, đa kho/chi nhánh và trả một phần. Các dịch vụ bên ngoài cần thông tin nhà cung cấp và cơ chế đối soát thật. COD, chuyển khoản và hoàn tiền ở bản này là nghiệp vụ nhân viên ghi nhận sau khi thực hiện thực tế.

Tài liệu này là hồ sơ kỹ thuật của phần mềm. Khi đưa vào báo cáo theo mẫu trường, bổ sung bìa, xác nhận giảng viên, mục lục và các biểu mẫu do môn học yêu cầu; không tự điền nhận xét hoặc điểm số.

**UC09 — QR và hoàn đơn hủy:** BankTransfer tạo payload từ snapshot ngân hàng và tổng tiền server. Khách báo đã chuyển; nhân viên đối soát số tiền/mã ngân hàng. Đơn hủy giữ chứng từ thu và chờ quản trị xác nhận hoàn thực tế. Báo cáo tách dòng tiền khỏi doanh thu hoàn tất. Chi tiết tại [PAYMENT_DELIVERY.md](PAYMENT_DELIVERY.md).
