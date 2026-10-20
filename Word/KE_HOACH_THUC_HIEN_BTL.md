> **Cập nhật triển khai 21/10/2026:** Phần mềm hiện dùng PHP Laravel, JavaScript và MySQL; đã có mua hàng online, tài khoản khách, VietQR, theo dõi giao hàng do cửa hàng cập nhật, chat theo danh mục, hết hạn QR, quên mật khẩu, email có hàng đợi, đánh giá đã mua và yêu cầu đổi/trả. Các mốc phạm vi bên dưới là kế hoạch ban đầu. Hồ sơ triển khai mới nhất: `shoe-store/docs/PROJECT.md`, `CUSTOMER_EXPERIENCE.md` và `docs/defense/`.

# MẪU KẾ HOẠCH THỰC HIỆN DỰ ÁN (BÀI TẬP LỚN)
**(Dành cho học phần: Lập trình Web - Học kỳ: I - Năm học: 2026–2027)**

---

**Tên đề tài/dự án:** Xây dựng website quản lý bán hàng (quản lý sản phẩm, nhập hàng, tồn kho, bán hàng, báo cáo doanh thu)

**Lớp học phần:** ................................................  **Mã nhóm:** ................................................

---

### Thông tin nhân sự nhóm

| STT | Họ và tên | Mã sinh viên | Vai trò trong nhóm | Nhiệm vụ chính phụ trách |
|---|---|---|---|---|
| 1 | [Nguyễn Văn An] | [MSV001] | Nhóm trưởng | Tài khoản/phân quyền, sản phẩm & biến thể, nhập kho, điều phối chung, tổng hợp báo cáo |
| 2 | [Trần Thị Bình] | [MSV002] | Thành viên | Khách hàng, bán hàng & thanh toán, dashboard & báo cáo, thiết kế slide |

> ⚠️ **Lưu ý:** Hãy thay thế thông tin trong `[...]` bằng thông tin thực của nhóm trước khi nộp.

---

## I. TỔNG QUAN DỰ ÁN (PROJECT OVERVIEW)

### 1. Lý do thực hiện dự án

**Ý tưởng xuất phát từ đâu?**
Trong bối cảnh kinh doanh hiện đại, nhiều cửa hàng bán lẻ vẫn đang quản lý hàng hóa, tồn kho và bán hàng theo cách thủ công hoặc bằng bảng tính Excel. Điều này dẫn đến sai sót trong kiểm kê, khó theo dõi lịch sử giao dịch, mất thời gian lập báo cáo và không kiểm soát được số lượng tồn theo từng phân loại sản phẩm.

**Bài toán thực tế cần giải quyết:**
Đề tài hướng đến tin học hóa toàn bộ quy trình vận hành của một cửa hàng bán hàng, bao gồm: quản lý sản phẩm theo biến thể (size, màu sắc), nhập hàng từ nhà cung cấp, cập nhật tồn kho tự động, lập đơn bán hàng tại quầy, ghi nhận thanh toán và tổng hợp báo cáo doanh thu. Hệ thống đồng thời hỗ trợ khách hàng xem sản phẩm và đặt hàng trực tuyến.

**Đối tượng hưởng lợi:**
- **Chủ cửa hàng / Quản trị viên:** Quản lý toàn bộ hoạt động, xem báo cáo doanh thu và tồn kho theo thời gian thực.
- **Nhân viên bán hàng:** Tra cứu sản phẩm, lập đơn bán và xử lý thanh toán nhanh chóng, chính xác.
- **Khách hàng (trực tuyến):** Xem danh sách sản phẩm, chọn biến thể và đặt hàng qua website.

---

### 2. Mục tiêu dự án (Theo tiêu chí SMART)

| Tiêu chí | Nội dung |
|---|---|
| **S – Specific (Cụ thể)** | Xây dựng một website quản lý bán hàng đầy đủ chức năng: quản lý tài khoản & phân quyền; danh mục & sản phẩm theo biến thể; nhập hàng từ nhà cung cấp; tồn kho theo SKU; lập đơn bán & thanh toán tại quầy; giao diện khách hàng đặt hàng online; dashboard và báo cáo doanh thu. |
| **M – Measurable (Đo lường được)** | Website có tối thiểu **9 phân hệ chức năng** bắt buộc; cơ sở dữ liệu có tối thiểu **14 bảng**; có tối thiểu **13 màn hình** giao diện; bộ test case kiểm thử tối thiểu **10 nhóm tình huống**; dữ liệu demo gồm **20–30 sản phẩm**, **3–5 nhà cung cấp**, giao dịch trải dài nhiều ngày. |
| **A – Achievable (Khả thi)** | Nhóm đã có kiến thức PHP/Laravel từ môn học; framework cung cấp sẵn ORM, phân quyền, validation và bảo vệ CSRF; MySQL phù hợp dữ liệu quan hệ; công cụ miễn phí có sẵn (VS Code, Figma, draw.io, Postman, GitHub). |
| **R – Relevant (Thực tế)** | Đề tài đáp ứng chuẩn đầu ra của môn học: thiết kế cơ sở dữ liệu quan hệ, lập trình web full-stack theo mô hình MVC, xử lý nghiệp vụ phức tạp (transaction, phân quyền, kiểm soát tồn kho), triển khai và kiểm thử ứng dụng thực tế. |
| **T – Time-bound (Thời hạn)** | Hạn chót nộp toàn bộ sản phẩm và báo cáo: **[DD/MM/YYYY]** — *(điền ngày thực tế theo lịch của giảng viên)*. |

---

## II. KẾ HOẠCH TRIỂN KHAI VÀ PHÂN CHIA CÔNG VIỆC (WBS)

| STT | Giai đoạn & Công việc cụ thể | Người phụ trách | Thời gian bắt đầu | Thời gian kết thúc | Kết quả đầu ra dự kiến (Deliverables) | Trạng thái |
|---|---|---|---|---|---|---|
| **1** | **GIAI ĐOẠN 1: KHỞI ĐỘNG** | | | | Biên bản thống nhất ý tưởng | |
| 1.1 | Họp nhóm, chọn đề tài & phân chia vai trò | Cả nhóm | Tuần 1 | Tuần 1 | Biên bản họp nhóm số 1 | ☐ |
| 1.2 | Lập và nộp kế hoạch thực hiện dự án này | Nhóm trưởng | Tuần 1 | Tuần 1 | Bản kế hoạch này | ☐ |
| **2** | **GIAI ĐOẠN 2: PHÂN TÍCH & THIẾT KẾ** | | | | ERD, wireframe, dự án khởi tạo | |
| 2.1 | Khảo sát quy trình nghiệp vụ; xác định yêu cầu chức năng & phi chức năng | An, Bình | Tuần 1 | Tuần 2 | Danh sách yêu cầu, use case tổng quát | ☐ |
| 2.2 | Thiết kế ERD và từ điển dữ liệu (14 bảng) | An | Tuần 2 | Tuần 2 | File ERD (draw.io/Figma) | ☐ |
| 2.3 | Wireframe các màn hình chính (13 màn hình) | Bình | Tuần 2 | Tuần 2 | File prototype (Figma) | ☐ |
| 2.4 | Khởi tạo dự án Laravel, cấu hình Git & GitHub, tạo migration | An | Tuần 2 | Tuần 2 | Repository GitHub; dự án chạy được ở local | ☐ |
| **3** | **GIAI ĐOẠN 3: THỰC THI — XÂY DỰNG SẢN PHẨM** | | | | Source code các phân hệ hoàn chỉnh | |
| 3.1 | Đăng nhập, đăng xuất, đổi mật khẩu; quản lý tài khoản nhân viên & phân quyền | An | Tuần 3 | Tuần 3 | Xác thực hoạt động; endpoint được bảo vệ quyền server-side | ☐ |
| 3.2 | Quản lý danh mục (loại sản phẩm, thương hiệu, kích cỡ, màu sắc) | An | Tuần 3 | Tuần 3 | CRUD danh mục hoàn chỉnh | ☐ |
| 3.3 | Quản lý sản phẩm: thêm/sửa/lọc, upload ảnh, ngừng kinh doanh | An | Tuần 3 | Tuần 3 | Quản lý được thông tin mẫu sản phẩm | ☐ |
| 3.4 | Quản lý biến thể SKU (size + màu, giá bán, tồn hiện tại) | An | Tuần 3 | Tuần 3 | Mỗi SKU có số tồn riêng; không trùng tổ hợp | ☐ |
| 3.5 | Quản lý nhà cung cấp | An | Tuần 4 | Tuần 4 | CRUD nhà cung cấp | ☐ |
| 3.6 | Phiếu nhập hàng: tạo nháp, thêm dòng chi tiết, xác nhận nhập trong transaction | An | Tuần 4 | Tuần 4 | Xác nhận nhập tăng đúng tồn theo SKU; không xác nhận hai lần | ☐ |
| 3.7 | Tồn kho: xem tồn theo SKU, cảnh báo tồn thấp, lịch sử biến động | An | Tuần 4 | Tuần 4 | Bảng lịch sử kho đầy đủ, đối chiếu khớp | ☐ |
| 3.8 | Quản lý khách hàng nội bộ: thêm/sửa/tìm kiếm, lịch sử mua hàng | Bình | Tuần 5 | Tuần 5 | CRUD khách hàng; hỗ trợ khách lẻ không đăng ký | ☐ |
| 3.9 | Lập đơn bán hàng tại quầy: chọn SKU, số lượng, kiểm tra tồn, tính tiền | Bình | Tuần 5 | Tuần 5 | Tạo đơn nháp thành công; từ chối bán vượt tồn | ☐ |
| 3.10 | Hoàn tất thanh toán: trừ tồn kho & ghi nhận payment trong cùng transaction | Bình | Tuần 5 | Tuần 5 | Luồng nhập → tồn → bán → doanh thu chạy xuyên suốt | ☐ |
| 3.11 | Trang sản phẩm cho khách trực tuyến: danh sách, lọc, chi tiết, giỏ hàng, đặt hàng | Bình | Tuần 6 | Tuần 6 | Khách đặt được đơn online; đơn xuất hiện trong hệ thống quản lý | ☐ |
| 3.12 | Dashboard tổng quan: doanh thu, số đơn, cảnh báo tồn thấp | Bình | Tuần 6 | Tuần 6 | Dashboard cập nhật số liệu thực từ CSDL | ☐ |
| 3.13 | Báo cáo: doanh thu theo thời gian, sản phẩm bán chạy, tồn thấp | Bình | Tuần 6 | Tuần 6 | Báo cáo đối chiếu khớp dữ liệu mẫu | ☐ |
| 3.14 | Tìm kiếm, lọc, phân trang cho toàn bộ danh sách | An, Bình | Tuần 6 | Tuần 6 | Tất cả bảng dài đều có tìm kiếm & phân trang | ☐ |
| **4** | **GIAI ĐOẠN 4: HOÀN THIỆN & KIỂM THỬ** | | | | Sản phẩm hoàn chỉnh, tài liệu đầy đủ | |
| 4.1 | Viết và thực thi bộ test case (xác thực, phân quyền, nghiệp vụ, đồng thời) | Cả nhóm | Tuần 7 | Tuần 7 | Tài liệu test case + kết quả kiểm thử | ☐ |
| 4.2 | Sửa lỗi, rà soát bảo mật (XSS, CSRF, SQL injection, upload file) | An | Tuần 7 | Tuần 7 | Danh sách lỗi đã xử lý | ☐ |
| 4.3 | Nhập dữ liệu mẫu (20–30 sản phẩm, 3–5 nhà cung cấp, giao dịch nhiều ngày) | Bình | Tuần 7 | Tuần 7 | File seeder/SQL dữ liệu demo | ☐ |
| 4.4 | Triển khai bản demo + viết hướng dẫn cài đặt (README.md) | An | Tuần 7 | Tuần 7 | Bản chạy thử; hướng dẫn cài đặt rõ ràng | ☐ |
| 4.5 | Gom code, viết báo cáo hoàn chỉnh theo cấu trúc yêu cầu | Cả nhóm | Tuần 7 | Tuần 8 | File báo cáo Word/PDF | ☐ |
| 4.6 | Thiết kế slide thuyết trình | Bình | Tuần 8 | Tuần 8 | File slide (PowerPoint/Canva) | ☐ |
| **5** | **GIAI ĐOẠN 5: NGHIỆM THU** | | | | Bảo vệ thành công | |
| 5.1 | Họp nhóm tập duyệt thuyết trình, phản biện thử (kịch bản demo 7–10 phút) | Cả nhóm | Tuần 8 | Tuần 8 | Video/Biên bản duyệt thử | ☐ |
| 5.2 | Chốt mã nguồn, chuẩn bị máy dự phòng + bản sao CSDL | Cả nhóm | Tuần 8 | Tuần 8 | Bộ sản phẩm cuối; demo được trên máy dự phòng | ☐ |
| 5.3 | Báo cáo trước giảng viên | Cả nhóm | Tuần 8 | Tuần 8 | Điểm số từ giảng viên | ☐ |

---

## III. CÁC MỐC KIỂM TRA TIẾN ĐỘ QUAN TRỌNG (MILESTONES)

- **Mốc 1 (Cuối Tuần 2):** Giảng viên duyệt Tên đề tài và Bản kế hoạch thực hiện dự án này. *(Cần có: ERD sơ bộ, danh sách chức năng, wireframe màn hình chính, repository GitHub đã khởi tạo)*
- **Mốc 2 (Cuối Tuần 4):** Giảng viên kiểm tra tiến độ phần thô — đạt tối thiểu **50% khối lượng công việc**. *(Cần có: Quản lý sản phẩm/biến thể hoàn chỉnh; nhập hàng làm tăng đúng tồn kho; đăng nhập & phân quyền hoạt động)*
- **Mốc 3 (Cuối Tuần 5):** Hạn chót hoàn thành nghiệp vụ cốt lõi. *(Cần có: Luồng nhập hàng → tồn kho → bán hàng → doanh thu chạy xuyên suốt; kiểm soát bán vượt tồn)*
- **Mốc 4 (Cuối Tuần 6):** Dừng thêm chức năng lớn — ưu tiên sửa lỗi và tài liệu. *(Cần có: Hoàn thành toàn bộ phạm vi bắt buộc, giao diện khách hàng online và báo cáo)*
- **Mốc 5 (Hạn nộp):** Hạn chót nộp toàn bộ sản phẩm và báo cáo cuối lên hệ thống.

> **Nguyên tắc quan trọng:** Viết báo cáo **song song mỗi tuần**, không dồn toàn bộ vào tuần cuối. Sau khi mỗi chức năng ổn định, chụp ảnh màn hình và ghi vào báo cáo ngay.

---

## IV. QUẢN LÝ RỦI RO (RISK MANAGEMENT)

| Tình huống rủi ro có thể xảy ra | Mức độ ảnh hưởng | Giải pháp phòng ngừa / Xử lý của nhóm |
|---|---|---|
| Thành viên nghỉ học, không làm bài, trễ deadline | Cao | Đặt deadline nội bộ sớm hơn hạn của GV **3 ngày**. Nhóm trưởng báo cáo GV nếu nhắc nhở quá 2 lần không phản hồi. |
| Máy tính hỏng, mất dữ liệu bài làm | Cao | Toàn bộ mã nguồn đẩy lên **GitHub** sau mỗi buổi làm; tài liệu lưu chung trên **Google Drive/OneDrive**; không lưu cục bộ trên máy cá nhân. |
| Sai tồn kho do thao tác lặp hoặc đồng thời | Cao | Dùng database **transaction** cho nhập kho & hoàn tất đơn bán; kiểm tra trạng thái trước khi xác nhận; không cho xác nhận hai lần cùng một phiếu/đơn. |
| Ôm quá nhiều chức năng, không kịp hoàn thành | Vừa | Chốt phạm vi bắt buộc từ tuần 2; chỉ thêm chức năng mở rộng sau khi luồng cốt lõi ổn định. Nếu cần cắt: bỏ mã giảm giá, thanh toán online trước tiên. |
| Chọn/gặp vướng mắc kỹ thuật với framework | Vừa | Dùng Laravel (stack đã học); làm thử luồng CRUD đơn giản trong tuần 2 để xác nhận; hỏi giảng viên hoặc tài liệu chính thức ngay khi bị kẹt. |
| Ý tưởng ban đầu quá tầm, không làm kịp | Vừa | Chủ động xin tham vấn giảng viên ở tuần 3 để thu hẹp phạm vi đề tài nếu thấy quá sức. |
| Báo cáo không khớp với phần mềm đã xây dựng | Vừa | Cập nhật tài liệu ngay sau khi mỗi chức năng ổn định; lưu ảnh chụp màn hình theo từng mốc. |
| Demo gặp sự cố kỹ thuật khi bảo vệ | Vừa | Chuẩn bị bản chạy local, bản sao CSDL đã có dữ liệu mẫu và **video demo dự phòng** sẵn sàng trình chiếu. |

---

## V. TIÊU CHÍ ĐÁNH GIÁ ĐÓNG GÓP (KPI & PEER REVIEW)

Để đảm bảo tính công bằng và triệt tiêu tình trạng "gánh tạ/núp bóng", cuối dự án các thành viên sẽ tự đánh giá lẫn nhau theo thang điểm % đóng góp:

- **Mức A (90% – 100%):** Hoàn thành xuất sắc nhiệm vụ, đúng hạn, chủ động hỗ trợ nhóm.
- **Mức B (70% – 89%):** Hoàn thành công việc được giao, đúng hạn nhưng ít có đóng góp sáng tạo thêm.
- **Mức C (50% – 69%):** Hoàn thành công việc nhưng trễ hạn, chất lượng kém, phải để người khác sửa lại nhiều.
- **Mức D (< 50%):** Không làm bài hoặc làm không đáng kể, không có tinh thần hợp tác.

> **(Điểm số cuối cùng của mỗi sinh viên = Điểm tổng của nhóm × % Đóng góp cá nhân)**

---

## PHỤ LỤC KỸ THUẬT

### Công nghệ sử dụng

| Thành phần | Công nghệ | Lý do lựa chọn |
|---|---|---|
| Backend | Laravel (PHP) | Phù hợp CRUD, phân quyền, validation, transaction; nhóm đã học |
| Frontend | Blade + HTML + CSS + JavaScript | Giảm độ phức tạp; tích hợp chặt với Laravel |
| Cơ sở dữ liệu | MySQL | Phù hợp dữ liệu quan hệ; hỗ trợ transaction nhập/bán hàng |
| Quản lý mã nguồn | Git + GitHub | Theo dõi tiến độ, phối hợp nhóm |
| Thiết kế | Figma + draw.io | Wireframe màn hình, ERD, sơ đồ UML |
| Kiểm thử | PHPUnit + Postman | Kiểm thử nghiệp vụ và tích hợp |
| Triển khai | Localhost / Shared Hosting PHP | Demo và hướng dẫn cài đặt |

### Kịch bản demo bảo vệ (~7–10 phút)

1. Giới thiệu vấn đề, đối tượng sử dụng và phạm vi đề tài.
2. Đăng nhập quản trị viên → tạo sản phẩm với hai size/màu sắc khác nhau.
3. Lập và xác nhận phiếu nhập hàng → chứng minh tồn tăng đúng theo SKU.
4. Đăng nhập nhân viên → lập đơn bán và hoàn tất thanh toán.
5. Kiểm tra tồn kho giảm đúng size đã bán và lịch sử biến động.
6. Thử bán vượt tồn → minh họa hệ thống từ chối giao dịch.
7. Khách hàng đặt hàng trực tuyến → đơn xuất hiện trong hệ thống quản lý.
8. Xem dashboard và báo cáo doanh thu đã cập nhật.
9. Giải thích phân quyền, cơ chế chống trừ kho hai lần và các giới hạn hiện tại.

### Tiêu chí hoàn thành

- [ ] Chạy được từ hướng dẫn cài đặt trên môi trường mới (README.md).
- [ ] Hoàn thành toàn bộ chức năng bắt buộc đã thống nhất.
- [ ] Luồng nhập → tồn kho → bán → doanh thu hoạt động nhất quán.
- [ ] Quản lý tồn đúng theo SKU/size/màu, không phát sinh tồn âm.
- [ ] Kiểm tra phân quyền tại máy chủ (server-side), không chỉ ẩn nút giao diện.
- [ ] Có kết quả kiểm thử và không còn lỗi nghiêm trọng trong luồng demo.
- [ ] Có mã nguồn, migration/seeder, dữ liệu mẫu, hướng dẫn cài đặt và sử dụng.
- [ ] Có báo cáo, slide và video demo dự phòng.
