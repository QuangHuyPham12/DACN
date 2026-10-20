# Hoàn thiện trải nghiệm khách hàng

Bản cập nhật ngày 21/10/2026 bổ sung thời hạn QR, khôi phục mật khẩu, email đơn hàng, đánh giá đã mua, yêu cầu đổi/trả và thư viện ảnh. Các migration chỉ bổ sung cấu trúc; dữ liệu cửa hàng cũ được giữ lại.

## Cập nhật và chạy tác vụ nền

Trong thư mục `shoe-store`:

```powershell
..\.tools\php\php.exe artisan migrate --force
..\.tools\php\php.exe artisan config:clear
.\start-worker.ps1
```

`start.ps1` cũng tự gọi `start-worker.ps1`. Tác vụ chạy ẩn trên Windows, tránh mở nhiều worker bằng PID, thời điểm bắt đầu tiến trình, đường dẫn PHP và thư mục dự án; không cần quyền truy vấn WMI/CIM. Trạng thái lưu trong `storage/logs/scheduler-worker.json`, PID ở `scheduler.pid`. Sau khi tắt máy cần mở lại `start.ps1`. Có thể chạy ở terminal riêng bằng `php artisan schedule:work`. Trên Linux hosting, thêm cron `* * * * * cd /duong-dan/shoe-store && php artisan schedule:run` theo cấu hình máy chủ. Không cần chạy cả hai cách trên cùng môi trường.

Kiểm tra thủ công: `php artisan schedule:list`, `php artisan store:expire-qr`, `php artisan store:send-order-mail`. Log tác vụ tại `storage/logs/scheduler.out.log` và `scheduler.error.log`. Không đưa log hoặc `.env` lên thư mục public.

## Hết hạn đơn QR

`STORE_QR_HOLD_MINUTES=30` là thời gian giữ hàng mặc định, giới hạn 5–1.440 phút. Chỉ đơn QR **mới**, online, chờ xác nhận, chưa báo chuyển khoản, chưa có payment và đã đến hạn mới tự hủy. Đơn được cửa hàng xác nhận, đã báo chuyển hoặc đã thu tiền cần đối soát thủ công. Đơn QR cũ có hạn để trống được giữ nguyên, không tự hủy hồi tố.

Tác vụ mỗi phút khóa đơn, hủy nguyên tử, trả đúng phần giữ chỗ và lượt mã giảm giá một lần. Trang thanh toán/theo dõi và thao tác báo chuyển/xác nhận cũng kiểm tra hết hạn. Sau hủy không hiện QR. Tiền đến muộn được ghi trên đơn hủy, chờ hoàn toàn bộ theo quy trình cũ; không khôi phục giữ kho. Không thể ngăn người dùng chuyển từ QR đã lưu ngoài website, nên cửa hàng vẫn phải theo dõi giao dịch đến muộn.

## Khôi phục mật khẩu

Trang đăng nhập có liên kết **Quên mật khẩu**. Email được chuẩn hóa, thông báo trả về giống nhau cho email có/không có tài khoản. Token ngẫu nhiên được băm trong bảng riêng `customer_password_reset_tokens`, dùng một lần, hết hạn 60 phút; có throttle và dọn token cũ. Tài khoản nhân viên dùng provider riêng, không bị reset qua luồng khách.

Liên kết sử dụng `APP_URL` cấu hình, không dùng Host của người gửi. Đặt lại mật khẩu khóa hàng token trong transaction, tăng `auth_version` và đổi remember token. Middleware kiểm tra phiên trên toàn bộ website, gồm checkout; phiên cũ sẽ bị đăng xuất ở yêu cầu kế tiếp. Đổi mật khẩu trong tài khoản cũng tăng phiên bản nhưng giữ phiên hiện tại. Không hiện liên kết reset công khai khi đang dùng log mail.

## Email và cấu hình SMTP

Khách có thể nhập email khi đặt hàng; khi đăng nhập, hệ thống dùng email của chính tài khoản. Email là thông tin nhận xác nhận, không dùng để gộp đơn hoặc cấp quyền xem lịch sử. Một bản ghi `order_messages` duy nhất trên mỗi đơn được tạo cùng transaction với đơn; tác vụ gửi sau đó. SMTP lỗi không làm mất đơn.

Mặc định `MAIL_MAILER=log`: email chỉ được ghi trong log nội bộ, **chưa gửi tới hộp thư thật**. Quản trị → **Email đơn hàng** phân biệt chờ, bản xem thử, máy chủ mail đã tiếp nhận và lỗi. Gửi lỗi tự thử tối đa 3 lần, cách nhau ít nhất 5 phút; admin có thể đưa email lỗi/bản xem thử về hàng đợi sau khi sửa cấu hình. Không tự gửi lại bản đã được SMTP tiếp nhận. Mất kết nối/crash đúng lúc SMTP đã nhận nhưng database chưa lưu vẫn có thể dẫn tới email trùng; không ảnh hưởng số đơn, kho hoặc tiền.

Điền nhà cung cấp SMTP của bạn trong `.env`, không gửi mật khẩu vào chat:

```dotenv
APP_URL=http://127.0.0.1:8000
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=may-chu-smtp-cua-ban
MAIL_PORT=587
MAIL_USERNAME=tai-khoan-smtp
MAIL_PASSWORD=mat-khau-ung-dung
MAIL_FROM_ADDRESS="email-gui-cua-ban"
MAIL_FROM_NAME="SOLE Footwear"
```

Dùng thông số TLS/cổng theo nhà cung cấp (465 thường dùng `MAIL_SCHEME=smtps`). Clear config và khởi động lại worker sau khi sửa. Với hosting cần APP_URL HTTPS có thể truy cập từ thiết bị nhận thư. Bản bàn giao chưa cấu hình SMTP thật, chưa xác minh khả năng vào hộp thư/chống spam. Email khôi phục gửi trực tiếp qua mailer, email đơn hàng qua hàng đợi riêng.

## Đánh giá sản phẩm

Chỉ tài khoản sở hữu đơn hoàn tất có chứa sản phẩm mới gửi được. Mỗi tài khoản một đánh giá/sản phẩm, 1–5 sao, 10–2.000 ký tự. Sửa nội dung đưa đánh giá về chờ duyệt. Chỉ đánh giá đã được admin duyệt mới hiển thị và tham gia điểm trung bình; admin có thể ẩn. Nhân viên không có quyền kiểm duyệt. Nội dung hiển thị dạng văn bản đã escape; công khai tên gọi ngắn, không email/địa chỉ. Không tạo đánh giá giả trong seed nền.

Lúc duyệt, server khóa đánh giá và so mã băm của nội dung với bản admin đang xem. Nếu khách vừa sửa nội dung hoặc mức sao, thao tác bị chặn và yêu cầu admin tải lại để đọc bản mới.

## Yêu cầu đổi size và trả hàng

Trong tài khoản, đơn hoàn tất có nút **Đổi size / trả hàng**. Khách mô tả lý do, size mong muốn khi đổi; một yêu cầu trên mỗi đơn. Đơn của người khác trả 404. Khách có thể quay lại xem trạng thái và phản hồi gần nhất của cửa hàng.

Nhân viên/admin tiếp nhận → trao đổi → đồng ý tiếp nhận hoặc từ chối; có thể mở lại yêu cầu từ chối để trao đổi. Đồng ý chưa tự hoàn tiền hay cộng kho. Admin kiểm tra hàng và lập phiếu trả theo quy trình tài chính hiện có. Chỉ khi có phiếu trả mới đóng yêu cầu đã xử lý; đơn đã đóng không chuyển ngược. Đổi size hiện thực hiện bằng trả **toàn bộ** đơn và lập đơn mua mới theo thỏa thuận, chưa có trả từng dòng hoặc tự bù chênh lệch. Không nhận ảnh khách gửi ở phiên bản này.

## Nội dung và ảnh sản phẩm

Admin có trường chất liệu, form, chăm sóc và hướng dẫn size riêng của từng mẫu. Không tự suy ra thông số từ tên hãng. Trường chưa điền được ghi rõ chưa xác nhận trên trang sản phẩm. Bảng size chung vẫn chỉ tham khảo.

Thêm tối đa 8 ảnh JPG/PNG/WebP, 4 MB mỗi ảnh; chọn nhiều ảnh, đổi ảnh bìa và xóa từng ảnh. Thêm ảnh giữ ảnh cũ. Ảnh lưu nội bộ, kiểm tra loại/tệp ở server; không nhận SVG hoặc URL tùy ý. Chụp các góc ngang, trên, gót và đế; nền sạch, cùng ánh sáng, đúng mẫu/màu. Chỉ dùng ảnh tự chụp hoặc được cấp quyền. Website hiện giữ ảnh minh họa khi chưa có ảnh thật đúng sản phẩm; không lấy ảnh đôi khác hoặc ảnh AI rồi gắn như ảnh hàng thật.

Giới hạn ảnh được kiểm tra khi giữ khóa sản phẩm để hai lượt tải đồng thời không vượt số lượng. Nếu transaction lưu ảnh bị lỗi, hệ thống xóa các tệp vừa lưu trong lượt đó; tệp/ảnh cũ được giữ nguyên. Mất điện hoặc dừng tiến trình đột ngột vẫn có thể để lại tệp chưa gắn bản ghi, nên thư mục ảnh cần được sao lưu và rà soát khi vận hành.

## Kiểm tra và hồ sơ

`CustomerExperienceTest` bổ sung kiểm tra hết hạn/giữ chỗ, tiền đến muộn, mail lỗi/gửi lặp, reset token/phiên cũ, quyền đánh giá/kiểm duyệt, yêu cầu riêng tư, điều kiện hoàn và thư viện ảnh. Kết quả đầy đủ ghi tại `TESTING.md`. SQL bàn giao được xuất lại sau migration. Các dịch vụ AI, ngân hàng và vận chuyển giữ nguyên phương án cấu hình/đối soát đã thống nhất.
