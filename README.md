# KẾ HOẠCH ĐỒ ÁN CHUYÊN NGÀNH
## Đề tài: Xây dựng website quản lý cửa hàng bán giày

> **Giả định lập kế hoạch:** Nhóm 1–2 sinh viên, thời gian 8 tuần, quản lý một cửa hàng và một kho. Chưa có thông tin về thời hạn, công nghệ bắt buộc và mẫu báo cáo của giảng viên.

## 1. Mục tiêu đề tài

Xây dựng website hỗ trợ cửa hàng quản lý sản phẩm giày, biến thể theo kích cỡ và màu sắc, nhập hàng, tồn kho, bán hàng và theo dõi doanh thu.

Kết quả cần đạt:
- Tin học hóa quy trình từ nhập hàng đến bán hàng và cập nhật tồn kho.
- Quản lý chính xác từng biến thể giày, không chỉ số lượng chung của mẫu giày.
- Phân quyền chủ cửa hàng và nhân viên theo nhiệm vụ.
- Cung cấp số liệu phục vụ quản lý và tra cứu lịch sử giao dịch.
- Có sản phẩm chạy được, cơ sở dữ liệu, tài liệu thiết kế và kết quả kiểm thử.

**Định hướng phạm vi:** Ưu tiên hệ thống quản lý nội bộ. Giao diện khách hàng đặt mua trực tuyến là phần mở rộng, không nên để phần giao diện bán hàng lấn át nghiệp vụ quản lý.

## 2. Phạm vi chức năng

### 2.1. Chức năng bắt buộc — phiên bản đủ để bảo vệ

| Phân hệ | Chức năng chính | Kết quả mong đợi |
|---|---|---|
| Tài khoản và phân quyền | Đăng nhập, đăng xuất, đổi mật khẩu; quản lý tài khoản nhân viên | Các API và thao tác được kiểm tra quyền ở máy chủ |
| Danh mục | Quản lý loại giày, thương hiệu, kích cỡ, màu sắc | Dữ liệu dùng chung thống nhất |
| Sản phẩm | Thêm, sửa, tìm kiếm, lọc, ảnh sản phẩm, ngừng kinh doanh | Lưu thông tin mẫu giày và các biến thể SKU |
| Tồn kho | Theo dõi số lượng theo SKU; cảnh báo tồn thấp; lịch sử tăng giảm | Phân biệt rõ size và màu của cùng một mẫu giày |
| Nhập hàng | Nhà cung cấp, phiếu nhập, chi tiết nhập, xác nhận phiếu | Tồn kho tăng đúng một lần khi xác nhận |
| Bán hàng | Lập đơn bán tại cửa hàng, chọn SKU, số lượng, khách hàng, thanh toán | Kiểm tra tồn, tính tiền và trừ kho chính xác |
| Khách hàng | Thông tin liên hệ, tìm kiếm, lịch sử mua hàng; hỗ trợ khách lẻ | Tra cứu giao dịch theo khách hàng |
| Báo cáo | Doanh thu theo thời gian, số đơn, sản phẩm bán chạy, tồn thấp | Chỉ tiêu có công thức và nguồn dữ liệu rõ ràng |

### 2.2. Chức năng mở rộng — thực hiện khi phần bắt buộc đã ổn định

**Ưu tiên 1:** In phiếu bán hàng, xuất báo cáo, nhật ký thao tác, đổi/trả hàng có kiểm soát.

**Ưu tiên 2:** Trang danh sách và chi tiết sản phẩm cho khách, giỏ hàng, đặt hàng, theo dõi đơn.

**Ưu tiên 3:** Mã giảm giá, thanh toán trực tuyến ở môi trường thử nghiệm, quét mã vạch.

**Không đưa vào phạm vi ban đầu:** Nhiều chi nhánh, nhiều kho, tích hợp vận chuyển thực tế, kế toán đầy đủ, hệ thống gợi ý AI.

> Nếu giảng viên yêu cầu website thương mại điện tử, cần chuyển chức năng khách hàng đặt hàng sang nhóm bắt buộc và giảm bớt các phần mở rộng khác.

## 3. Vai trò và phân quyền

| Nghiệp vụ | Chủ cửa hàng / quản trị viên | Nhân viên |
|---|---|---|
| Quản lý tài khoản, phân quyền | Có | Không |
| Quản lý danh mục và sản phẩm | Có | Chỉ xem |
| Quản lý nhà cung cấp, xác nhận nhập hàng | Có | Không trong phiên bản đầu |
| Xem tồn kho | Có | Có |
| Lập đơn bán, ghi nhận thanh toán | Có | Có |
| Tra cứu khách hàng | Có | Có, phục vụ bán hàng |
| Xem báo cáo toàn cửa hàng | Có | Không |
| Điều chỉnh kho, xử lý giao dịch sai | Có, có lý do và nhật ký | Không |

Khách hàng trực tuyến là vai trò bổ sung nếu triển khai phần bán hàng online.

## 4. Quy tắc nghiệp vụ quan trọng

### 4.1. Sản phẩm và biến thể
- Một mẫu giày có nhiều biến thể theo **size + màu sắc**.
- Mỗi biến thể có SKU duy nhất, giá bán và số lượng tồn riêng.
- Không được tạo trùng tổ hợp sản phẩm, size và màu.
- Sản phẩm đã có giao dịch chỉ được ngừng kinh doanh, không xóa mất lịch sử.

**Ví dụ:** Mẫu “Sneaker A” có SKU màu trắng–size 40 tồn 5 đôi; màu trắng–size 41 tồn 2 đôi. Bán size 40 chỉ giảm tồn của đúng SKU đó.

### 4.2. Nhập kho
- Phiếu nhập gồm trạng thái nháp và đã xác nhận.
- Lưu phiếu nháp chưa làm tăng tồn kho.
- Xác nhận phiếu nhập cập nhật tồn kho và lịch sử kho trong cùng một giao dịch cơ sở dữ liệu.
- Phiếu đã xác nhận không được sửa hoặc xóa trực tiếp; sai sót được xử lý bằng nghiệp vụ điều chỉnh có lưu lý do.

### 4.3. Bán hàng và thanh toán
- Luồng cơ bản: **đơn nháp → thanh toán thành công → hoàn tất**; đơn nháp có thể hủy.
- Chỉ trừ tồn khi hoàn tất bán hàng; kiểm tra lại tồn ngay tại thời điểm này.
- Không cho phép bán số lượng vượt tồn hoặc phát sinh tồn âm.
- Ghi nhận đơn, thanh toán và trừ kho phải nhất quán; tránh cập nhật dở dang.
- Bấm xác nhận hai lần không được tạo thanh toán hoặc trừ kho hai lần.
- Đơn đã hoàn tất không hủy như đơn nháp; nếu chưa triển khai đổi/trả thì khóa thao tác này và nêu rõ giới hạn.
- Lưu giá bán thực tế tại chi tiết đơn để thay đổi giá sản phẩm không ảnh hưởng lịch sử.
- Sử dụng kiểu số thập phân phù hợp cho tiền, không dùng số thực dấu phẩy động.

### 4.4. Báo cáo
- Phiên bản cơ bản: doanh thu là tổng tiền của các đơn hoàn tất trong khoảng thời gian được chọn.
- Đơn nháp, đơn hủy không được tính doanh thu.
- Nếu bổ sung hoàn tiền, doanh thu thuần phải trừ các khoản hoàn tiền theo quy tắc được mô tả rõ.
- Không gọi doanh thu là lợi nhuận. Báo cáo lợi nhuận chỉ bổ sung khi đã xác định cách tính giá vốn.

## 5. Công nghệ đề xuất

### Phương án phù hợp nếu chưa có yêu cầu bắt buộc

| Thành phần | Đề xuất | Lý do |
|---|---|---|
| Backend | Laravel, phiên bản ổn định còn được hỗ trợ | Phù hợp CRUD, kiểm tra dữ liệu, phân quyền, giao dịch dữ liệu |
| Frontend | Blade + HTML + CSS + JavaScript | Giảm độ phức tạp so với tách riêng frontend/backend |
| Cơ sở dữ liệu | MySQL | Phù hợp dữ liệu quan hệ và giao dịch nhập–bán |
| Quản lý mã nguồn | Git, GitHub | Theo dõi tiến độ và phối hợp nhóm |
| Thiết kế | Figma, draw.io hoặc công cụ tương đương | Thiết kế màn hình, ERD, UML |
| Kiểm thử | Bộ kiểm thử của framework; Postman nếu có API | Kiểm thử nghiệp vụ và tích hợp |
| Triển khai | Máy chủ hỗ trợ PHP/MySQL hoặc môi trường nội bộ | Có bản demo và hướng dẫn cài đặt |

**Không bắt buộc dùng Laravel:** Nếu môn học yêu cầu Java, C# hoặc JavaScript, có thể thay bằng Spring Boot, ASP.NET Core hoặc Node.js. Nên chọn công nghệ nhóm đã học và chốt trước khi bắt đầu tuần 2.

**Kiến trúc:** Ứng dụng nguyên khối theo MVC; tách logic nhập hàng, hoàn tất bán hàng và cập nhật kho khỏi phần xử lý giao diện. Không cần microservices.

## 6. Thiết kế dữ liệu sơ bộ

| Bảng | Vai trò và dữ liệu chính |
|---|---|
| users | Tài khoản nhân viên, mật khẩu đã băm, vai trò, trạng thái |
| categories | Loại giày |
| brands | Thương hiệu |
| products | Tên mẫu giày, danh mục, thương hiệu, mô tả, trạng thái |
| product_images | Ảnh của sản phẩm |
| sizes | Kích cỡ; phiên bản đầu thống nhất một hệ size |
| colors | Màu sắc |
| product_variants | Sản phẩm, size, màu, SKU, giá bán, tồn hiện tại, ngưỡng tồn thấp |
| suppliers | Nhà cung cấp |
| purchase_orders | Phiếu nhập, nhà cung cấp, người lập, trạng thái, thời điểm xác nhận |
| purchase_order_items | Biến thể, số lượng, đơn giá nhập |
| customers | Thông tin khách hàng |
| sales_orders | Đơn bán, khách hàng tùy chọn, nhân viên, trạng thái, tổng tiền, thời điểm hoàn tất |
| sales_order_items | Biến thể, số lượng, đơn giá bán tại thời điểm giao dịch |
| payments | Đơn bán, phương thức, số tiền, trạng thái, thời điểm thanh toán |
| inventory_movements | SKU, lượng tăng/giảm, loại nghiệp vụ, chứng từ tham chiếu, người thực hiện |

Quan hệ chính:
- Danh mục/thương hiệu có nhiều sản phẩm.
- Sản phẩm có nhiều biến thể và nhiều ảnh.
- Phiếu nhập/đơn bán có nhiều dòng chi tiết; mỗi dòng tham chiếu một biến thể.
- Biến thể có nhiều bản ghi biến động kho.
- Khách hàng có nhiều đơn bán; đơn khách lẻ có thể không gắn khách hàng.

Ràng buộc cần có:
- Khóa ngoại, SKU duy nhất, tổ hợp sản phẩm–size–màu duy nhất.
- Số lượng giao dịch phải là số nguyên dương, giá không âm.
- Tồn hiện tại và lịch sử kho được cập nhật đồng thời bằng transaction.
- Nếu lưu tổng tiền trong đơn, tính lại phía máy chủ từ chi tiết, không tin tổng tiền do trình duyệt gửi.

Đây là thiết kế sơ bộ; cần hoàn thiện ERD, kiểu dữ liệu và các ràng buộc sau khi chốt nghiệp vụ.

## 7. Các màn hình cần thiết

1. Đăng nhập và đổi mật khẩu.
2. Trang tổng quan: doanh thu, số đơn, cảnh báo tồn thấp.
3. Danh sách sản phẩm, bộ lọc và biểu mẫu thêm/sửa.
4. Chi tiết sản phẩm, quản lý size/màu/SKU.
5. Danh mục và thương hiệu.
6. Danh sách nhà cung cấp.
7. Danh sách, tạo mới và chi tiết phiếu nhập.
8. Tồn kho và lịch sử biến động theo SKU.
9. Màn hình bán hàng: tìm SKU, thêm dòng hàng, tổng tiền, thanh toán.
10. Danh sách và chi tiết đơn bán.
11. Khách hàng và lịch sử mua hàng.
12. Quản lý nhân viên.
13. Báo cáo theo khoảng thời gian.

Giao diện cần thống nhất bố cục, có trạng thái tải/rỗng/lỗi, thông báo xác nhận rõ ràng và dùng tốt trên laptop. Các bảng dài cần tìm kiếm, lọc và phân trang.

## 8. Tiến độ thực hiện trong 8 tuần

| Tuần | Công việc | Sản phẩm bàn giao / điều kiện hoàn thành |
|---|---|---|
| 1 | Khảo sát quy trình cửa hàng; xác định vấn đề, phạm vi, vai trò, yêu cầu | Đề cương, danh sách chức năng, use case tổng quát; giảng viên xác nhận hướng đi |
| 2 | Thiết kế ERD, quy tắc nghiệp vụ, luồng nhập/bán, wireframe; khởi tạo dự án | ERD, từ điển dữ liệu, prototype màn hình chính, dự án chạy được |
| 3 | Đăng nhập, phân quyền, danh mục, sản phẩm, biến thể, ảnh | Quản lý được SKU; kiểm tra quyền và dữ liệu đầu vào |
| 4 | Nhà cung cấp, phiếu nhập, xác nhận nhập, tồn kho và lịch sử kho | Nhập hàng làm tăng đúng tồn; không xác nhận lặp |
| 5 | Khách hàng, lập đơn bán, thanh toán, chi tiết đơn | Luồng nhập → bán → tồn kho chạy xuyên suốt; kiểm tra bán vượt tồn |
| 6 | Dashboard, báo cáo, tìm kiếm/lọc/phân trang; bổ sung tính năng ưu tiên nếu còn thời gian | Đối chiếu báo cáo với dữ liệu mẫu; hoàn thành phạm vi bắt buộc |
| 7 | Kiểm thử, sửa lỗi, rà soát bảo mật, triển khai thử, hoàn thiện báo cáo | Bộ test, danh sách lỗi đã xử lý, bản chạy thử, báo cáo gần hoàn chỉnh |
| 8 | Dự phòng, chốt mã nguồn, hoàn thiện tài liệu, slide, video và tập bảo vệ | Bộ sản phẩm cuối; demo được trên máy dự phòng |

### Mốc kiểm soát
- **Cuối tuần 2:** Chốt phạm vi; thay đổi lớn phải đánh đổi với chức năng khác.
- **Cuối tuần 4:** Hoàn thành quản lý sản phẩm và nhập kho.
- **Cuối tuần 5:** Có bản demo nghiệp vụ cốt lõi xuyên suốt.
- **Cuối tuần 6:** Dừng thêm chức năng lớn, ưu tiên sửa lỗi và tài liệu.
- Viết báo cáo song song mỗi tuần, không dồn toàn bộ vào tuần cuối.

### Phân công nếu nhóm có 2 người
- **Thành viên A:** Tài khoản/phân quyền, sản phẩm/biến thể, nhà cung cấp, nhập kho.
- **Thành viên B:** Khách hàng, bán hàng/thanh toán, dashboard và báo cáo.
- **Cùng thực hiện:** Chốt schema, quy tắc kho, review code, kiểm thử tích hợp, tài liệu và bảo vệ.
- Mỗi người phụ trách cả giao diện và backend của phân hệ mình để hạn chế chờ đợi.

Nếu làm một mình, giữ chức năng bắt buộc và hoãn bán hàng online, thanh toán trực tuyến, mã giảm giá.

## 9. Kế hoạch kiểm thử

| Nhóm kiểm thử | Tình huống tiêu biểu |
|---|---|
| Xác thực | Đúng/sai mật khẩu; chưa đăng nhập truy cập trang quản trị; đăng xuất |
| Phân quyền | Nhân viên gọi trực tiếp endpoint quản lý tài khoản hoặc xem doanh thu phải bị từ chối |
| Sản phẩm | Trùng SKU, trùng size–màu, giá âm, thiếu tên, ảnh không hợp lệ |
| Nhập kho | Phiếu nháp không tăng tồn; xác nhận tăng đúng SKU; xác nhận lần hai không tăng tiếp |
| Bán hàng | Số lượng bằng tồn, vượt tồn, bằng 0 hoặc âm; sửa tổng tiền ở phía trình duyệt |
| Đồng thời | Hai phiên cùng mua SKU chỉ còn một đôi: chỉ một giao dịch được hoàn tất |
| Tính nhất quán | Lỗi giữa quá trình hoàn tất đơn không để lại thanh toán hoặc kho cập nhật một phần |
| Báo cáo | Đối chiếu doanh thu với đơn hoàn tất; loại đơn nháp/hủy; kiểm tra mốc đầu/cuối ngày |
| Lịch sử | Đổi tên/giá hoặc ngừng kinh doanh sản phẩm không làm sai chứng từ cũ |
| Giao diện | Dữ liệu rỗng, dữ liệu dài, phân trang, thông báo lỗi, màn hình nhỏ |

Dữ liệu demo đề xuất: khoảng 20–30 mẫu giày, nhiều size/màu, 3–5 nhà cung cấp và giao dịch ở nhiều ngày. Dùng dữ liệu giả lập, không sử dụng thông tin cá nhân thật.

Yêu cầu bảo mật cơ bản:
- Băm mật khẩu bằng cơ chế chuẩn của framework.
- Kiểm tra đầu vào và quyền ở phía máy chủ.
- Sử dụng ORM hoặc truy vấn có tham số; phòng chống XSS và CSRF phù hợp cơ chế xác thực.
- Giới hạn loại/kích thước tệp ảnh; không cho phép thực thi tệp tải lên.
- Không đưa mật khẩu, khóa bí mật hoặc cấu hình môi trường thật lên Git.
- Có hướng dẫn sao lưu và khôi phục dữ liệu demo.

## 10. Cấu trúc báo cáo dự kiến

Điều chỉnh theo mẫu bắt buộc của khoa/giảng viên.

1. **Tổng quan đề tài:** Lý do chọn đề tài, mục tiêu, đối tượng, phạm vi, phương pháp và kế hoạch.
2. **Cơ sở lý thuyết và công nghệ:** Kiến trúc MVC, dữ liệu quan hệ, công nghệ sử dụng và lý do lựa chọn.
3. **Phân tích và thiết kế:** Khảo sát, yêu cầu chức năng/phi chức năng, phân quyền, use case, activity/sequence diagram cho luồng chính, ERD và thiết kế giao diện.
4. **Xây dựng hệ thống:** Cấu trúc dự án, các phân hệ, cách xử lý biến thể, tồn kho, thanh toán và transaction.
5. **Kiểm thử và đánh giá:** Môi trường, test case, kết quả thực tế, đối chiếu mục tiêu, lỗi và cách xử lý.
6. **Kết luận và hướng phát triển:** Kết quả đạt được, hạn chế, phần có thể mở rộng.

Phụ lục: hướng dẫn cài đặt, tài khoản demo, từ điển dữ liệu, hướng dẫn sử dụng, tài liệu tham khảo.

## 11. Kịch bản demo bảo vệ — khoảng 7–10 phút

1. Giới thiệu vấn đề, đối tượng sử dụng và phạm vi.
2. Đăng nhập quản trị viên; tạo mẫu giày với hai size khác nhau.
3. Lập và xác nhận phiếu nhập; chỉ ra tồn tăng theo từng SKU.
4. Đăng nhập nhân viên; lập đơn bán và hoàn tất thanh toán.
5. Kiểm tra tồn kho giảm đúng size đã bán và lịch sử biến động.
6. Thử bán vượt tồn để minh họa kiểm soát nghiệp vụ.
7. Xem đơn hàng và báo cáo doanh thu đã cập nhật.
8. Giải thích phân quyền, cách chống trừ kho hai lần và giới hạn hiện tại.

Chuẩn bị sẵn dữ liệu, tài khoản hai vai trò, bản sao cơ sở dữ liệu và video demo dự phòng.

## 12. Tiêu chí hoàn thành

- [ ] Chạy được từ hướng dẫn cài đặt trên môi trường mới.
- [ ] Hoàn thành toàn bộ chức năng bắt buộc đã thống nhất.
- [ ] Luồng nhập → tồn kho → bán → doanh thu hoạt động nhất quán.
- [ ] Quản lý tồn theo đúng SKU/size/màu, không phát sinh tồn âm.
- [ ] Kiểm tra phân quyền ở máy chủ, không chỉ ẩn nút giao diện.
- [ ] Có kết quả kiểm thử và không còn lỗi nghiêm trọng trong luồng demo.
- [ ] Có mã nguồn, migration/script tạo dữ liệu, dữ liệu mẫu, hướng dẫn cài đặt và sử dụng.
- [ ] Có báo cáo, slide và bản demo dự phòng.

## 13. Rủi ro và phương án giảm thiểu

| Rủi ro | Biện pháp |
|---|---|
| Ôm quá nhiều chức năng | Chốt phần bắt buộc; chỉ mở rộng sau khi luồng cốt lõi ổn định |
| Sai tồn kho do thao tác lặp hoặc đồng thời | Transaction, kiểm tra tồn nguyên tử/khóa phù hợp, kiểm soát chuyển trạng thái |
| Chọn công nghệ chưa quen | Ưu tiên stack đã học; làm thử một luồng CRUD trong tuần 2 |
| Báo cáo không khớp phần mềm | Cập nhật tài liệu theo từng mốc, lưu ảnh sau khi chức năng ổn định |
| Trễ tiến độ | Dự phòng tuần 8; bỏ tính năng mở rộng thay vì giảm kiểm thử |
| Demo gặp sự cố | Có bản local, dữ liệu sao lưu và video dự phòng |

## 14. Thông tin cần chốt để cá nhân hóa kế hoạch

1. Bạn làm cá nhân hay nhóm, có bao nhiêu thành viên?
2. Hạn nộp và các mốc duyệt đề cương/kiểm tra tiến độ là khi nào?
3. Môn học yêu cầu hoặc bạn muốn sử dụng công nghệ nào?
4. Giảng viên yêu cầu quản lý nội bộ hay phải có cả khách hàng đặt mua online?
5. Có mẫu báo cáo hoặc tiêu chí chấm điểm cụ thể không?
