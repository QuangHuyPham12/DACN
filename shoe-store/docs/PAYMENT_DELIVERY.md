# Thanh toán VietQR và theo dõi giao hàng

## Điền tài khoản ngân hàng

Trong file `.env` ở thư mục `shoe-store`, điền bốn dòng đã chuẩn bị:

```dotenv
PAYMENT_BANK_BIN=
PAYMENT_BANK_NAME=
PAYMENT_ACCOUNT_NUMBER=
PAYMENT_ACCOUNT_NAME=
```

| Biến | Cần điền |
|---|---|
| PAYMENT_BANK_BIN | Mã BIN NAPAS của ngân hàng nhận, đúng 6 chữ số; tra cứu từ ngân hàng/VietQR chính thức |
| PAYMENT_BANK_NAME | Tên ngân hàng, đặt trong dấu ngoặc kép nếu có khoảng trắng |
| PAYMENT_ACCOUNT_NUMBER | Số tài khoản nhận tiền, giữ nguyên số 0 ở đầu, 6–25 chữ số; không phải số thẻ |
| PAYMENT_ACCOUNT_NAME | Tên chủ tài khoản, đặt trong dấu ngoặc kép; hệ thống chuyển sang chữ in hoa không dấu |

Không nhập mật khẩu, PIN hoặc OTP. Bản giao không có tài khoản ngân hàng thật hoặc tài khoản minh họa để nhận tiền. Cấu hình trống/sai làm lựa chọn QR bị vô hiệu hóa, COD vẫn dùng được. Tham số được đọc tại `config/payment.php`.

Sau khi lưu:

```powershell
..\.tools\php\php.exe artisan config:clear
```

Trên máy khác có PHP trong PATH, dùng `php artisan config:clear`. Tạo **đơn mới** để kiểm tra. Mỗi đơn QR giữ bản chụp ngân hàng nhận tại lúc tạo; sửa `.env` không làm thay đổi thông tin của đơn cũ. Kiểm tra thông tin được ứng dụng ngân hàng nhận diện trước khi dùng nhận tiền thực tế. Chưa có giao dịch ngân hàng thật trong quá trình kiểm thử.

## Luồng khách hàng

1. Chọn sản phẩm/size/màu, thêm giỏ, nhập người nhận, số điện thoại và địa chỉ.
2. Chọn COD hoặc chuyển khoản QR, kiểm tra giá/ưu đãi/phí giao, bấm **Đặt hàng & tới thanh toán**.
3. COD: thấy số tiền sẽ thanh toán khi nhận. QR: quét bằng ứng dụng ngân hàng, kiểm tra người nhận, số tiền và nội dung `SOLEDHxxxxxx`.
4. Có thể tải QR PNG (để mở bằng chức năng quét từ ảnh trong app ngân hàng có hỗ trợ), sao chép tài khoản và nội dung. Sau khi đã chuyển thực tế, bấm **Tôi đã chuyển khoản** để gửi thông báo.
5. **Chờ đối soát** chưa phải **Đã thanh toán**. Chỉ sau khi nhân viên kiểm tra tiền về, trạng thái mới đổi.
6. Mở **Theo dõi giao hàng**: xem bước hiện tại, đơn vị vận chuyển, mã vận đơn, điểm xử lý gần nhất, thời gian và nhật ký. Bấm cập nhật để lấy thông tin mới.

QR theo cấu trúc VietQR/NAPAS EMVCo, mã tiền tệ VND 704, có số tiền máy chủ và CRC16-CCITT. Thư viện QR được đóng gói tại máy; không gửi tài khoản, số tiền hoặc mã đơn tới website tạo QR bên ngoài. Không có webhook ngân hàng và không tự kiểm tra số dư.

Trang thanh toán và theo dõi dùng token ngẫu nhiên, có `private, no-store` và `no-referrer`. Giữ riêng liên kết vì người có liên kết có thể xem thông tin nhận hàng, báo đã chuyển và hủy đơn chưa được xác nhận/chưa báo chuyển khoản.

## Luồng cửa hàng

Mở **Quản trị → Đơn bán hàng → Chi tiết đơn**:

- QR: xem thông báo của khách; đối chiếu tài khoản nhận, nội dung, đúng tổng tiền với sao kê. Nhập **số tiền thực tế** và **mã giao dịch ngân hàng**, rồi xác nhận nhận tiền. Mã được chuẩn hóa in hoa, không thể gắn vào hai đơn.
- Cập nhật tuần tự: xác nhận → chuẩn bị → bàn giao vận chuyển → đã giao. Đơn QR chưa đối soát không được bàn giao vận chuyển.
- Nhập đơn vị vận chuyển, mã vận đơn, điểm xử lý và ghi chú trong **Cập nhật vị trí / vận đơn**. Mỗi lần lưu có sự kiện kèm thời gian/người xử lý và xuất hiện cho khách. Chức năng này không tự thay đổi bước giao hàng.
- COD chỉ ghi tiền khi hoàn tất sau giao thành công. QR đã thu trước được dùng lại khi hoàn tất, không thu lần hai. Hoàn tất xuất kho và giải phóng giữ chỗ một lần.

Tiến trình và điểm xử lý do nhân viên nhập theo thông tin thực tế; **chưa đồng bộ API hoặc GPS từ đơn vị vận chuyển**. Không có dự đoán ngày giao hoặc bản đồ giả.

## Giữ hàng, hủy và hoàn tiền

- COD giữ hàng khi nhân viên xác nhận đơn. QR giữ ngay khi tạo đơn để không nhận tiền cho lượng đã bán hết.
- Kho sổ sách chỉ xuất khi đơn hoàn tất, kể cả QR đã nhận tiền. Số có thể bán trừ toàn bộ lượng giữ chỗ.
- Tác vụ nền tự hủy đơn QR mới sau hạn (mặc định 30 phút), nếu chưa báo chuyển, chưa thu tiền và chưa được cửa hàng xác nhận. Đơn cũ không có hạn được giữ nguyên; đơn đã báo chuyển cần đối soát thủ công. Khách có thể hủy từ liên kết theo dõi hoặc tài khoản khi còn chờ xác nhận, chưa báo chuyển khoản và chưa thu tiền.
- Đơn đã báo chuyển khoản hoặc đã thu tiền cần cửa hàng đối soát trước khi hủy. Trước bàn giao, nhân viên có thể hủy; sau bàn giao chỉ hủy sau khi nhận lại đủ hàng giao thất bại.
- Đơn hủy đã thu tiền giữ nguyên chứng từ thu, chuyển sang **Chờ hoàn tiền**. Quản trị viên hoàn tiền thực tế rồi nhập mã giao dịch hoàn để xác nhận. Hoàn **toàn bộ tổng đã thu**, gồm phí giao hàng.
- Nếu tiền đến sau khi đơn đã hủy, nhân viên vẫn ghi nhận được khoản thu muộn trên đơn đó để quản trị hoàn lại. Không khôi phục đơn hoặc giữ kho trở lại.
- Trả hàng sau đơn hoàn tất dùng quy trình phiếu trả cũ: hoàn toàn bộ tiền hàng sau giảm giá, không hoàn phí giao. Hai quy trình không trừ tiền hoàn hai lần.
- Chỉ hỗ trợ thanh toán đủ một lần và hoàn toàn bộ; chưa có thu thiếu/thừa, nhiều lần thanh toán hoặc hoàn một phần.

## Dữ liệu và báo cáo

Migration `2026_10_21_000003_add_qr_payments.php` bổ sung cột, không xóa đơn cũ. Migration `000004_complete_customer_experience` thêm hạn QR và các bảng khôi phục mật khẩu/đánh giá/hỗ trợ/email; bản hiện tại có 34 bảng. Xem [CUSTOMER_EXPERIENCE.md](CUSTOMER_EXPERIENCE.md).

`sales_orders`: `payment_method`, `bank_snapshot` JSON, `transfer_reported_at`, `shipping_carrier`, `delivery_location`.

`payments`: một khoản thu duy nhất/đơn; `bank_reference` duy nhất, `verified_by`, `paid_at`; đơn hủy đã hoàn có `status=refunded`, `refunded_at`, `refund_reference` duy nhất, `refunded_by`. `amount` luôn giữ nguyên số tiền gốc đã thu.

Doanh thu dựa trên đơn hoàn tất, trừ phiếu trả theo ngày lập. **Dòng tiền** tính khoản thu theo `paid_at`, trừ khoản hoàn đơn hủy theo `refunded_at` và tiền trả hàng theo ngày lập phiếu. Báo cáo hiển thị riêng tiền trả trước của đơn đang xử lý và tiền chờ hoàn của đơn hủy; hai số này là dư hiện tại, không bị lọc theo khoảng ngày.

## Kiểm thử

`QrPaymentTest` kiểm tra tổng tiền/tài khoản phía server, chống giả dữ liệu, thiếu cấu hình, nội dung TLV/CRC, snapshot ngân hàng, báo chuyển khoản không ghi tiền, token riêng, hủy giải phóng giữ chỗ, phân quyền đối soát/hoàn, đúng số tiền, mã giao dịch trùng, không giao khi chưa thu QR, không thu hai lần, hoàn đơn hủy, tiền đến muộn và giao thất bại. Vị trí vận chuyển được kiểm tra nhật ký và thoát HTML.

```powershell
php scripts/check_concurrency.php --qr-verify
php scripts/check_concurrency.php --qr-verify --same-order
php scripts/check_concurrency.php --mysql --qr-verify
php scripts/check_concurrency.php --mysql --qr-verify --same-order
```

Hai process cạnh tranh một mã ngân hàng: chỉ một khoản thu. Cùng đơn gửi hai lần: cả hai trả thành công, vẫn một khoản thu. Test dựng database riêng, không chuyển tiền.

Ảnh QR kiểm thử trong `docs/screenshots/qr-layout-test-full.jpg` có nhãn tài khoản giả, được giải mã bằng jsQR và đối chiếu chính xác payload máy chủ. Trang HTML kiểm thử tạm đã xóa; dữ liệu giả không được cấu hình làm ngân hàng nhận trên website. Kiểm tra responsive ở chiều rộng 390px, chưa thay cho thử ứng dụng ngân hàng/thiết bị thật.
