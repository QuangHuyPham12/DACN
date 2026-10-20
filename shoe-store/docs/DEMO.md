# Kịch bản trình bày đồ án SOLE

Đề tài: **Xây dựng website quản lý cửa hàng bán giày bằng PHP, JavaScript và MySQL**.

Báo cáo Word/PDF, slide và mã tạo tài liệu nằm trong [hồ sơ bảo vệ](defense/README.md). Bản kiểm thử hiện đạt 81 ca / 779 assertions trên SQLite và MySQL.

## Các chức năng khách hàng mới

- Chạy `start.ps1` để bật cả web và scheduler; `start-worker.ps1` nhận diện worker đang chạy để tránh mở trùng. `php artisan schedule:list` hiển thị tác vụ hết hạn QR, email và dọn token.
- Mở **Đăng nhập → Quên mật khẩu**. Trình bày thời hạn 60 phút, token băm/dùng một lần và việc phiên cũ mất hiệu lực sau đặt lại. Chế độ mail log chỉ tạo thư nội bộ; không gửi SMTP thật. Không mở log chứa liên kết riêng tư khi quay màn hình.
- Đăng nhập khách demo, mở **Tài khoản → Đơn hàng → DH000034 → Đổi/trả**. Đây là đơn hoàn tất DEMO, có yêu cầu đổi size 39 sang 40 và phản hồi DEMO của cửa hàng. Không có giao hàng/hoàn tiền thực tế.
- Admin vào **Yêu cầu đổi/trả** để xem phản hồi; việc đồng ý chưa thay đổi tiền hoặc kho. Chỉ đóng sau khi có phiếu trả; đổi size lập đơn mua mới.
- Mở **Air Force Everyday → Đánh giá** để thấy đánh giá DEMO đã được duyệt. Nội dung mới hoặc chỉnh sửa phải được admin kiểm duyệt; admin phải tải lại nếu khách vừa sửa sau lúc mở trang.
- Vào sửa sản phẩm để trình bày chất liệu/form/chăm sóc/size và thư viện tối đa 8 ảnh. Chỉ bổ sung ảnh thật đúng mẫu khi đã có nguồn; dữ liệu đang dùng ảnh minh họa.
- Admin vào **Email đơn hàng** để thấy hàng đợi và phân biệt bản xem thử với thư đã được SMTP chấp nhận. Không bấm gửi/retry như minh chứng đã gửi email thật.
- Hết hạn QR được minh họa bằng test tự động trong database riêng; không sửa giờ hệ thống hoặc đối soát tiền giả trên website. Đơn cũ không có hạn và đơn đã báo chuyển/thu/xác nhận được bảo toàn.

## Chuẩn bị

1. Mở `shoe-store`, chạy `start.ps1`. Nếu cổng 8000 đang chạy, mở thẳng website.
2. Trình bày trang cửa hàng và trang quản trị ở hai tab; thử kích thước điện thoại.
3. Đăng nhập admin@sole.vn / Demo@12345. Khách: khach@sole.vn / Demo@12345. Nhân viên: staff@sole.vn / Demo@12345.
4. Giải thích dữ liệu và ảnh là minh họa. MySQL local tại cổng 3309; database website là sole_store.
5. Tránh ghi lại video chứa .env hoặc mật khẩu MySQL.

## Luồng 1 — Khách mua trực tuyến

- Trang chủ: giới thiệu bộ sưu tập, ưu đãi đang hiệu lực.
- Lọc size, thương hiệu, khoảng giá và chỉ hàng có sẵn; thử không có kết quả rồi bỏ lọc.
- Mở sản phẩm: thay size/màu, kiểm tra giá và số có thể bán; mở bảng hướng dẫn size.
- Lưu yêu thích sau khi đăng nhập.
- Thêm vào giỏ, cập nhật số lượng; áp SOLE10. Mã giảm 10%, tối đa 150.000đ, từ 500.000đ.
- Ví dụ một đôi Suede Classic giá 1.700.000đ: giảm 150.000đ, giao miễn phí, tổng 1.550.000đ.
- Đặt COD, xem mã đơn, dòng tiền và lịch sử. Tại thời điểm này chưa trừ/giữ hàng.
- Vào tài khoản để xem đơn; thử tự hủy chỉ trước khi cửa hàng xác nhận.

## Luồng 2 — Xử lý đơn và giữ hàng

Trước khi xử lý đơn, có thể trình bày SOLE Assistant: hỏi “Nike size 40 dưới 1 triệu”, hỏi tiếp “Chỉ size 41”, mở thẻ và kiểm tra size đã chọn sẵn. Thử “Không giới hạn giá”, “Bỏ hết tiêu chí”, phí giao và bảng size. Nhãn cho biết đang tư vấn theo danh mục; chỉ khi tự cấu hình API mới bật mô hình AI. Xem [ADVISOR.md](ADVISOR.md).

- Admin mở đơn mới, xác nhận & giữ hàng. Xem tồn sổ sách không đổi, giữ chỗ tăng, số có thể bán giảm.
- Chuyển sang chuẩn bị, ghi mã vận đơn và bàn giao giao hàng.
- Trang khách cập nhật tiến trình. Hệ thống không cho hủy trực tiếp khi đang giao.
- Xác nhận đã giao rồi ghi nhận đã nhận đủ tiền. Tồn sổ sách giảm, giữ chỗ được giải phóng, thanh toán ghi một lần.
- Gửi lại thao tác hoàn tất không tạo thanh toán hoặc xuất kho lần hai.
- Nếu giao thất bại: ghi nhận chưa thành công, thử giao lại; hoặc xác nhận nhận đủ hàng hoàn về rồi hủy, trả lại lượng giữ và lượt mã.

## Luồng 3 — Quản lý cửa hàng

- Sản phẩm: thêm/sửa mẫu, tải ảnh JPG/PNG/WebP, SKU-size-màu, giá và ngưỡng.
- Không chỉnh tồn trực tiếp ở biểu mẫu sản phẩm.
- Tạo phiếu nhập nháp: tồn chưa tăng. Xác nhận nhận hàng: tồn tăng đúng một lần.
- POS: chọn khách hoặc khách lẻ, nhiều SKU và mã ưu đãi. Lưu nháp rồi xác nhận tiền mặt/chuyển khoản đã nhận.
- In phiếu bán, tra lịch sử khách và lọc tồn thấp.
- Kiểm kê: lập phiếu +/−, ghi lý do và kiểm tra chứng từ/sổ kho; số tồn sau không thấp hơn lượng giữ.

## Luồng 4 — Trả hàng và báo cáo

- Chọn đơn hoàn tất. Admin ghi nhận trả toàn bộ đơn sau khi đã nhận hàng và hoàn tiền thực tế.
- Chọn nhập lại kho chỉ khi hàng đủ điều kiện bán; hàng hỏng không cộng lại tồn.
- Tiền hoàn = tiền hàng − giảm giá. Phí giao không hoàn.
- Đơn gốc giữ nguyên để đối chiếu; phiếu trả có mã, thời gian, lý do và người thực hiện.
- Báo cáo: so tiền bán đã thu, khoản đã hoàn, thực thu; chọn khoảng ngày và xuất CSV mở bằng Excel.
- Giải thích khoản hoàn tính theo ngày trả, có thể liên quan đơn kỳ trước; thực thu có thể âm và không phải lợi nhuận.

## Luồng 5 — Phân quyền và an toàn

- Khách không vào admin. Khách chỉ thấy lịch sử gắn với tài khoản của mình.
- Nhân viên tra cứu kho, quản lý khách và xử lý đơn; không sửa sản phẩm, nhập/điều chỉnh kho, hoàn tiền, quản lý ưu đãi/nhân viên hay xuất báo cáo.
- SKU trùng/tổ hợp size-màu trùng bị chặn; CSRF và validation phía máy chủ.
- Số tiền do PHP tính từ dữ liệu SKU, không nhận tổng từ JavaScript.
- Chạy hai PHP process mua đôi cuối để chứng minh chống bán âm; chạy hai process hoàn tất cùng đơn để chứng minh không ghi lặp.

## Câu hỏi thường gặp khi bảo vệ

**PHP và JavaScript phân công thế nào?** Laravel/PHP xử lý quyền và nghiệp vụ; JavaScript tăng tương tác giao diện; MySQL lưu và ràng buộc dữ liệu. JavaScript hiển thị ước tính, PHP quyết định kết quả.

**Tồn sổ sách và giữ chỗ khác gì?** Sổ sách là lượng hàng chưa xuất bán; giữ chỗ là phần dành cho đơn COD đã xác nhận hoặc QR vừa tạo, kể cả đang giao chưa thu tiền. Có thể bán bằng chênh lệch hai số.

**Vì sao vẫn có SQLite?** Đó là bản nguồn cũ và môi trường kiểm thử nhanh. Website bàn giao chạy MySQL; cùng bộ test được chạy trên MySQL thực tế.

**Thanh toán online chưa tích hợp có phải thiếu?** Hiện có COD và QR VietQR theo tài khoản cấu hình, số tiền/nội dung từ máy chủ, thông báo đã chuyển và nhân viên đối soát. Chưa có xác nhận tiền tự động từ ngân hàng. Kết nối ngân hàng/cổng thanh toán cần tài khoản nhà cung cấp, chữ ký webhook và đối soát; không giả lập kết nối đó.

**Đổi size thế nào?** Trả toàn bộ đơn theo thống nhất với cửa hàng, lập đơn mới đúng SKU. Phiên bản này chưa trả từng dòng hoặc tự động bù chênh lệch.

## Luồng QR và vị trí giao hàng

Điền ngân hàng của mình theo [PAYMENT_DELIVERY.md](PAYMENT_DELIVERY.md). Tạo đơn mới chọn QR, xem số tiền/nội dung và việc giữ hàng ngay khi tạo. Bấm báo đã chuyển chưa có payment. Trong môi trường thử, dùng test tự động để giả lập đã nhận tiền; khi vận hành chỉ xác nhận theo sao kê thật. Cập nhật đơn vị/mã vận đơn/vị trí trên admin, mở trang theo dõi khách để thấy thay đổi. Đơn DH000033 là demo đang giao với vị trí/mã giả, không có vận chuyển thật.
