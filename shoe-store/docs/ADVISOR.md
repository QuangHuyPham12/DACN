# Tư vấn chọn giày và kết nối AI

## Chế độ bàn giao

Theo lựa chọn của chủ đồ án, website chạy **tư vấn theo danh mục, chưa kết nối AI sinh ngôn ngữ**. Không cần API key và không gửi cuộc trò chuyện tới nhà cung cấp bên ngoài. Đây là bộ phân tích câu hỏi tiếng Việt cùng truy vấn database và quy tắc cửa hàng, không phải mô hình ngôn ngữ được huấn luyện riêng. Nhãn trong khung chat ghi rõ chế độ đang dùng.

SOLE Assistant xuất hiện ở góc dưới trang khách hàng, gồm trang chủ, đăng nhập, thương hiệu, danh sách/chi tiết sản phẩm, giỏ và tài khoản. Khách chưa đăng nhập cũng được tư vấn.

## Các câu hỏi có thể thử

- “Nike size 40 dưới 1 triệu” → lọc đồng thời hãng, size, ngân sách và tồn có thể bán.
- “Chỉ size 41” → giữ hãng/ngân sách trước đó, đổi size.
- “Từ 700k đến 1tr5”, “tầm 800k”, “1,5 triệu”, “1 triệu rưỡi” → nhận diện cách viết số tiền thường dùng. “Tầm” được hiểu là mức tối đa và ghi rõ trong tiêu chí.
- “Màu trắng”, “không chọn Nike” → lọc màu hoặc loại trừ hãng.
- “Giày đi học”/“đi làm” → tra nhóm sneaker/casual; “chạy bộ” → nhóm giày chạy bộ. Đây là lọc danh mục, không chứng nhận hiệu năng thể thao.
- “Không giới hạn giá”, “bất kỳ hãng nào”, “bỏ hết tiêu chí” → mở điều kiện rõ ràng.
- “Phí ship thế nào?”, “có ưu đãi không?”, “hướng dẫn chọn size”, “đổi trả thế nào?” → trả lời từ cấu hình và chính sách thực tế của bản đồ án.
- Nút “Hỏi SOLE về đôi giày này” → tư vấn mẫu đang xem.

Mỗi thẻ lấy từ database: sản phẩm đang bán, SKU đang bán, tồn − giữ chỗ > 0. Giá, size, màu và tồn thuộc **cùng một SKU**. Mở thẻ sẽ chọn sẵn SKU đó trên trang sản phẩm. Tồn được kiểm tra lại khi thêm giỏ/đặt hàng, vì chat không giữ hàng.

Không tự nới bộ lọc khi không có kết quả. Tư vấn size chỉ là hướng dẫn đo và bảng tham khảo, không hứa chắc chắn vừa. Dữ liệu và ảnh là minh họa đồ án nên không khẳng định hàng chính hãng, xuất xứ hoặc bảo hành.

## Lịch sử, dữ liệu và quyền

- Lưu tối đa 12 lượt trong session phía máy chủ, cùng tiêu chí hội thoại. Không tạo thêm bảng database, không ghi vào localStorage.
- Lịch sử đi theo phiên trình duyệt. “Bắt đầu lại” xóa lịch sử/tiêu chí của phiên; đăng xuất khách cũng xóa chat.
- Giới hạn 1.000 ký tự/câu, 12 yêu cầu tư vấn/phút/IP. API dùng CSRF và không cho đọc thông tin đơn hàng hay tài khoản khác.
- Chat chỉ đọc sản phẩm và chính sách công khai; không đặt/hủy đơn, đổi tồn hoặc hoàn tiền.
- JavaScript hiển thị nội dung bằng textContent; không thực thi HTML hoặc link do mô hình sinh ra. Link/thẻ sản phẩm do máy chủ tạo và giới hạn cùng website.
- Session vẫn thuộc phạm vi dữ liệu cần bảo vệ/sao lưu của cửa hàng. Không nhập mật khẩu, thông tin thanh toán hoặc thông tin cá nhân vào chat.

## Bật OpenAI khi đã có API key

1. Tự thêm vào `.env` trên máy chạy server, không dán khóa vào chat, JavaScript hoặc Git:

~~~dotenv
OPENAI_API_KEY=khóa-của-bạn
OPENAI_MODEL=gpt-4.1-mini
~~~

2. Chạy `php artisan config:clear`, khởi động lại server PHP và tải lại website. Có thể chọn model khác trong tài khoản nếu model đó hỗ trợ Responses API với văn bản đầu vào/đầu ra; cần kiểm thử lại khi đổi model.
3. Mở chat, kiểm tra nhãn “AI · Có tra cứu danh mục”. Khi kết nối lỗi, nhãn đổi sang “AI tạm gián đoạn · Đang tra cứu danh mục” và hệ thống dùng trả lời theo quy tắc để khách tiếp tục tra cứu.

Server gọi `POST https://api.openai.com/v1/responses`, `store=false`, tối đa 600 output tokens, timeout 15 giây. Khóa chỉ dùng ở PHP. Dữ liệu gửi gồm câu hỏi, tối đa sáu lượt hội thoại trước, tiêu chí, sản phẩm công khai liên quan và câu trả lời quy tắc đã xác minh; không lấy bảng khách hàng/đơn/nhân viên hoặc `.env` vào ngữ cảnh. Khách được thông báo việc gửi chat tới OpenAI ngay trước ô nhập khi bật chế độ này. `store=false` không phải cam kết không có lưu giữ ở mọi hệ thống của nhà cung cấp; cần xem chính sách tài khoản thực tế.

AI giúp diễn đạt và giải thích; thẻ sản phẩm/giá vẫn do PHP lấy trực tiếp từ database. Trả lời bằng mô hình có thể sai, không thay cho xác nhận giao dịch. Xóa `OPENAI_API_KEY` rồi clear config và khởi động lại để trở về chế độ không gọi dịch vụ ngoài.

Tài liệu API chính thức đã tham chiếu: [Create a model response — OpenAI](https://developers.openai.com/api/reference/resources/responses/methods/create).

## Kiểm tra

CustomerShoppingTest kiểm tra lọc cùng SKU, các cách viết ngân sách, hỏi tiếp/reset, loại trừ hãng, màu, hàng hết/ẩn, chính sách, phân quyền dữ liệu, lịch sử và giới hạn request. Phần OpenAI dùng HTTP fake để kiểm tra cấu trúc request, khóa không lộ ra HTML, lỗi 401 và lỗi kết nối chuyển về chế độ danh mục. **Chưa gọi OpenAI thực bằng khóa thật**, vì bản bàn giao chưa có API key.

Trang đăng nhập giữ giỏ và hỗ trợ quay về giỏ sau đăng nhập/đăng ký bằng đích định sẵn; không nhận URL chuyển hướng tùy ý từ người dùng.

Chính sách chat đã cập nhật COD/VietQR, bước thanh toán và theo dõi vị trí/vận đơn do cửa hàng nhập. Chat không xác nhận giao dịch ngân hàng hoặc truy vấn đơn riêng. Cấu hình thanh toán tại [PAYMENT_DELIVERY.md](PAYMENT_DELIVERY.md).
