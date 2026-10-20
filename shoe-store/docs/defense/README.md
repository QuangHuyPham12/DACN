# Hồ sơ bảo vệ SOLE

Đề tài: **Xây dựng website quản lý cửa hàng bán giày bằng PHP, JavaScript và MySQL**. Cập nhật 21/10/2026, theo mã nguồn đang chạy trong thư mục `shoe-store`.

## Tệp sử dụng

| Tệp | Nội dung |
|---|---|
| [Báo cáo Word](BAO_CAO_KY_THUAT_SOLE.docx) | 13 trang kỹ thuật, có thể chỉnh sửa thông tin nhóm theo hồ sơ chính thức |
| [Báo cáo PDF](BAO_CAO_KY_THUAT_SOLE.pdf) | Bản xuất từ Microsoft Word, đã kiểm tra bố cục từng trang |
| [Slide bảo vệ hiện tại](SLIDE_BAO_VE_SOLE_V2.pptx) | 12 slide, chữ và thẻ nội dung chỉnh sửa được; có ghi chú thuyết trình |
| [Nguồn nội dung báo cáo](BAO_CAO_KY_THUAT_SOLE.md) | Bản văn bản để đối chiếu nội dung và hình |
| [Kịch bản demo](../DEMO.md) | Luồng trình bày và các câu hỏi thường gặp |
| [Kiểm thử](../TESTING.md) | 81 ca / 779 assertions trên SQLite và MySQL, cùng giới hạn kiểm tra |

Tệp slide không có hậu tố V2 là bản trước khi bổ sung kiểm tra cuối; sử dụng **V2** khi trình bày. Báo cáo này là báo cáo kỹ thuật, cần đối chiếu mẫu nộp và yêu cầu hình thức cụ thể của giảng viên. Không tự điền mã sinh viên, điểm hoặc nhận xét giảng viên. Chưa tạo video quay thao tác; có kịch bản demo và ảnh chụp website để chuẩn bị phần trình bày.

## Chuẩn bị chạy

Chạy `start.ps1` trong thư mục dự án. Website tại [127.0.0.1:8000](http://127.0.0.1:8000); quản trị tại [127.0.0.1:8000/admin](http://127.0.0.1:8000/admin). MySQL website là `sole_store`, cổng 3309; database test `sole_store_test` được tách riêng. Bản SQL dữ liệu hiện có 24 mẫu, 72 SKU, 34 đơn và 30 thanh toán; đơn và phản hồi mới đều có nhãn DEMO.

Chức năng mới: đơn QR hết hạn, quên mật khẩu/thu hồi phiên, email có hàng đợi, đánh giá đã mua cần duyệt, yêu cầu đổi/trả riêng tư, thông số từng mẫu và thư viện tối đa 8 ảnh. Chi tiết cấu hình tại [CUSTOMER_EXPERIENCE.md](../CUSTOMER_EXPERIENCE.md).

Ngân hàng nhận tiền và API AI để trống theo lựa chọn của chủ đồ án; SMTP chưa cấu hình. Email chỉ tạo bản xem thử trong log. Ảnh hàng hiện là minh họa, cần thay bằng ảnh đúng sản phẩm đã được phép sử dụng. Vị trí vận chuyển do cửa hàng cập nhật; chưa có webhook ngân hàng, GPS hoặc API vận chuyển. Không trình bày những tích hợp này như đã giao dịch thật.

## Nguồn tạo tài liệu

- `scripts/build_defense_report.py`: tạo Word, Markdown và các sơ đồ bằng python-docx/Pillow.
- `scripts/build_defense_slides.mjs`: tạo slide bằng runtime `@oai/artifact-tool` của Codex. Đặt `RUNTIME_NODE_MODULES` tới thư mục module của runtime để chạy; đường dẫn bộ kiểm tra tài liệu/Python trong script dành cho máy đang bàn giao. Đổi tên output/receipt trước khi tạo phiên bản tiếp theo để giữ bản đã kiểm tra.
- Các ảnh giao diện lấy từ `docs/screenshots`, sơ đồ PNG nằm cùng thư mục này. Không có ảnh hàng thật hoặc nhận xét khách thật được bịa để làm bằng chứng.
- `validation-slides.json`: kết quả kiểm tra cấu trúc, bố cục, phông Arial và nhập lại 12 slide. Đây là kiểm tra tệp, không khẳng định đã thử trình chiếu trên mọi phiên bản PowerPoint.

Website chạy bình thường mà không cần các thư viện tạo báo cáo/slide. Tài liệu gốc trong `BaoCaoBTL` và `Word` được dùng làm nguồn đối chiếu; bản Word gốc không bị ghi đè.
