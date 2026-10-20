import fs from 'node:fs/promises';
import path from 'node:path';
import {pathToFileURL,fileURLToPath} from 'node:url';
import {createRequire} from 'node:module';
const runtimeModules=process.env.RUNTIME_NODE_MODULES;
if(!runtimeModules) throw new Error('Set RUNTIME_NODE_MODULES to the Codex bundled Node modules directory.');
const runtimeRequire=createRequire(path.join(runtimeModules,'sole-defense-loader.cjs'));
const {Presentation,PresentationFile}=await import(pathToFileURL(runtimeRequire.resolve('@oai/artifact-tool')).href);
const root=path.resolve(path.dirname(fileURLToPath(import.meta.url)),'..');
const out=path.join(root,'docs/defense');
const build=path.join(root,'storage/app/defense-build');
const skill='C:/Users/quang/.codex/plugins/cache/openai-primary-runtime/presentations/26.1015.12040/skills/presentations';
const deck=Presentation.create({slideSize:{width:1280,height:720}});
const C={bg:'#F7F5F0',ink:'#24291F',green:'#324E3F',muted:'#667061',orange:'#BC673A',white:'#FFFFFF'};
function box(s,x,y,w,h,fill=C.white){return s.shapes.add({geometry:'roundRect',position:{left:x,top:y,width:w,height:h},fill,line:{fill:'none',width:0}});}
function text(s,value,x,y,w,h,size=26,color=C.ink,bold=false){const t=s.shapes.add({geometry:'textbox',position:{left:x,top:y,width:w,height:h},fill:'none',line:{fill:'none',width:0}});t.text=value;t.text.style={typeface:'Arial',fontSize:size,color,bold};return t;}
function page(title,source='docs/PROJECT.md'){const s=deck.slides.add();s.background.fill=C.bg;text(s,'SOLE  /  ĐỒ ÁN CHUYÊN NGÀNH',60,30,900,26,14,C.green,true);text(s,title,60,84,1160,100,42,C.ink,true);text(s,'PHP · JavaScript · MySQL',60,676,600,22,13,C.muted);text(s,String(deck.slides.items.length).padStart(2,'0')+' / 12',1120,676,100,22,13,C.muted);s.speakerNotes.text='Nguồn nội bộ: '+path.join(root,source)+'. Bản triển khai ngày 21/10/2026. Dữ liệu DEMO, không có giao dịch thật.';return s;}
async function img(s,file,x,y,w,h){const b=await fs.readFile(path.join(out,file));s.images.add({blob:b.buffer.slice(b.byteOffset,b.byteOffset+b.byteLength),contentType:'image/png',alt:file.replace('.png',''),fit:'contain',position:{left:x,top:y,width:w,height:h}});}
let s=page('Website quản lý cửa hàng bán giày');
text(s,'SOLE',60,220,620,160,118,C.green,true);
text(s,'Quản lý theo size và màu\nKết nối bán tại quầy với mua trực tuyến',65,410,1080,110,31);
text(s,'Nhóm 6 · Phạm Quang Huy và Đào Ngọc Tú',65,560,1100,45,23,C.muted);
s.speakerNotes.text+=' Giới thiệu phạm vi một cửa hàng, một kho. Giảng viên theo đề cương: ThS. Mai Văn Linh. Thời lượng trình bày khoảng 10 phút.';
s=page('Ba vai trò và quyền được kiểm tra ở server');
for(const [i,title,body] of [[0,'Khách hàng','Lọc giày và đặt hàng\nTài khoản, đánh giá\nYêu cầu đổi size / trả'],[1,'Nhân viên','POS và xử lý đơn\nCập nhật giao hàng\nPhản hồi hỗ trợ'],[2,'Quản trị viên','Sản phẩm và kho\nHoàn tiền, phân quyền\nBáo cáo và kiểm duyệt']]){let x=60+i*395;box(s,x,215,370,370);text(s,title,x+24,245,320,60,29,C.green,true);text(s,body,x+24,335,320,200,25);}
s=page('PHP xử lý nghiệp vụ và MySQL lưu giao dịch');await img(s,'architecture.png',60,180,1160,460);
s=page('Database có ràng buộc cho kho đơn và tiền','docs/DATABASE.md');await img(s,'data-relations.png',60,180,1160,460);
s=page('QR có thời hạn và đối soát tiền thực nhận','docs/CUSTOMER_EXPERIENCE.md');await img(s,'payment-flow.png',60,180,1160,460);
s=page('Giữ hàng và xuất kho là hai thời điểm khác nhau','docs/DATABASE.md');
for(const [i,title,a,b,c] of [[0,'Trước đơn','10','0','10'],[1,'Giữ 2 đôi','10','2','8'],[2,'Hoàn tất','8','0','8']]){let x=60+i*395;box(s,x,215,370,360);text(s,title,x+24,240,320,50,27,C.green,true);text(s,`Tồn sổ sách        ${a}\nGiữ chỗ                ${b}\nCó thể bán           ${c}`,x+24,330,325,180,29);}
text(s,'Chỉ hoàn tất mới xuất sổ sách. Gửi lại thao tác không tạo giao dịch thứ hai.',60,605,1160,45,23,C.muted);
s=page('Khách chọn giày theo hãng size màu và ngân sách');
text(s,'Danh mục và giỏ hàng\n\nGiá theo đúng SKU\n\nCOD hoặc VietQR\n\nTheo dõi tiến trình giao',60,225,420,340,26);await img(s,'storefront-screen.png',495,185,725,440);
s=page('Khôi phục tài khoản và thu hồi phiên cũ','docs/CUSTOMER_EXPERIENCE.md');
text(s,'Token băm, một lần\nHết hạn 60 phút\n\nKhông lộ email tồn tại\n\nMật khẩu đổi làm phiên cũ mất hiệu lực',60,225,410,350,26);await img(s,'password-screen.png',485,185,735,440);
s=page('Đánh giá chỉ hiển thị sau khi được duyệt','docs/CUSTOMER_EXPERIENCE.md');
text(s,'Chỉ khách đã mua\n\nMột đánh giá mỗi mẫu\n\nSửa lại cần duyệt lại\n\nAdmin duyệt hoặc ẩn',60,225,410,350,26);await img(s,'review-screen.png',485,185,735,440);
s=page('Yêu cầu đổi trả có phản hồi riêng cho khách','docs/CUSTOMER_EXPERIENCE.md');
text(s,'Gửi từ đơn của mình\n\nTheo dõi phản hồi\n\nChấp thuận chưa hoàn tiền\n\nĐóng sau khi có phiếu trả',60,225,410,350,25);await img(s,'support-screen.png',485,185,735,440);
s=page('81 ca kiểm thử đạt trên SQLite và MySQL','docs/TESTING.md');
box(s,60,205,550,210,C.green);text(s,'81',88,220,490,100,86,C.white,true);text(s,'ca kiểm thử đạt',90,340,470,48,27,C.white);
box(s,650,205,570,210);text(s,'779',680,220,510,100,86,C.green,true);text(s,'assertions được xác minh',682,340,490,48,27);
text(s,'Kho và tiền · phân quyền · token và phiên\nHết hạn QR · email lỗi · đánh giá · yêu cầu đổi trả',60,470,1160,130,28);
s=page('Chạy local và cấu hình các dịch vụ thật','docs/CUSTOMER_EXPERIENCE.md');
box(s,60,205,550,390);text(s,'Bản chạy hiện tại',86,230,500,60,29,C.green,true);text(s,'start.ps1 bật website và tác vụ nền\n\nMySQL sole_store · cổng 3309\n\nSao lưu SQL, ảnh và .env riêng',86,325,490,220,25);
box(s,650,205,570,390);text(s,'Cần dữ liệu của cửa hàng',676,230,515,60,29,C.green,true);text(s,'Ngân hàng nhận tiền và SMTP\n\nẢnh thật đúng sản phẩm\n\nAPI AI nếu cần sinh ngôn ngữ\n\nVận chuyển hiện cập nhật thủ công',676,315,510,250,24);
for(let i=0;i<deck.slides.items.length;i++){const png=await deck.export({slide:deck.slides.items[i],format:'png',scale:1});await fs.writeFile(path.join(build,`slide-${String(i+1).padStart(2,'0')}.png`),new Uint8Array(await png.arrayBuffer()));console.log('Rendered slide',i+1);}
const candidate=path.join(build,'SOLE-draft.pptx');await (await PresentationFile.exportPptx(deck)).save(candidate);
const {finalizePresentation}=await import(pathToFileURL(path.join(skill,'container_tools/artifact_tool_utils.mjs')).href);
const finalPath=path.join(out,'SLIDE_BAO_VE_SOLE_V2.pptx');
const result=await finalizePresentation({workspaceDir:root,candidatePath:candidate,finalPath,pythonExecutable:'C:/Users/quang/.cache/codex-runtimes/codex-primary-runtime/dependencies/python/python.exe',integrityValidatorPath:path.join(skill,'container_tools/inspect_presentation_package_integrity.py'),layoutValidatorPath:path.join(skill,'container_tools/inspect_presentation_layout_geometry.py'),layoutArgs:['--expected-slide-size-emu','12192000,6858000','--validate-heading-fit'],requiredNativeTableOwnerSlides:[],fontPolicy:{basis:'design',families:['Arial']},verifyArtifactToolImport:true,receiptPath:path.join(build,'slides-validation-v2.json')});
console.log(JSON.stringify(result));
