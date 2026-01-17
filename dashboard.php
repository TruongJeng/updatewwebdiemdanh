<?php
require_once __DIR__ . '/config/session.php';
$timeout = 18000;

if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['last_active']) && (time() - $_SESSION['last_active'] > $timeout)) {
        session_unset();
        session_destroy();
        header("Location: index.php?timeout=1");
        exit();
    }
    $_SESSION['last_active'] = time();
} else {
    header("Location: index.php");
    exit();
}
require_once __DIR__ . '/includes/db.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
if (!empty($_SESSION['full_name'])) {
    $full_name = $_SESSION['full_name'];
} else {
    $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $full_name = $stmt->fetchColumn();
    $_SESSION['full_name'] = $full_name;
}
if ($_SESSION['first_login'] ?? 0) {
    header("Location: change_password.php");
    exit();
}
$pageTitle = "CLB Kỹ năng Đoàn - Hội Trường THPT Lý Thường Kiệt";
include __DIR__ . '/includes/header.php';

?>
<style>
/* Sidebar và responsive sidebar-toggle */
.sidebar {
  background: #f4faff;
  width: 230px;
  min-height: 100vh;
  position: fixed;
  top: 60px; left: 0;
  border-right: 1.5px solid #a8c8f0;
  padding-top: 16px;
  transition: left 0.2s;
  z-index: 1090;
}
.sidebar ul { list-style: none; padding: 0; margin: 0;}
.sidebar li { margin-bottom: 7px; }
.sidebar a {
  display: flex; align-items: center;
  color: #3178c6;
  text-decoration: none;
  padding: 10px 18px;
  border-radius: 8px 0 0 8px;
  font-size: 16px;
  transition: background 0.13s, color 0.13s;
}
.sidebar a:hover, .sidebar a.active {
  background: #a8c8f0;
  color: #1757a6;
}
.sidebar i { font-size: 18px; margin-right: 9px; }
.main {
  margin-left: 230px;
  padding: 40px 32px 24px 32px;
  transition: margin-left 0.2s;
}
#sidebarToggle {
  display: none;
}
@media (max-width: 900px) {
  .sidebar {
    width: 210px;
    left: -210px;
    top: 0;
    min-height: 100vh;
    border-radius: 0 18px 18px 0;
    box-shadow: 2px 0 12px #0001;
    position: fixed;
    transition: left 0.22s cubic-bezier(.42,0,.58,1.0);
    z-index: 1200;
  }
  .sidebar.active { left: 0; }
  .main { margin-left: 0; padding: 16px 4vw 24px 4vw; }
  #sidebarToggle {
    display: flex !important;
    background: none;
    border: none;
    color: #fff;
    font-size: 2rem;
    margin-right: 10px;
    margin-left: -10px;
    cursor: pointer;
    align-items: center;
    justify-content: center;
    height: 42px;
    width: 42px;
    position: fixed;
    top: 12px;
    left: 12px;
    z-index: 2001;
  }
  body.sidebar-open { overflow: hidden; }
  .sidebar-backdrop {
    display: block;
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(40,60,110,0.18);
    z-index: 1190;
  }
}
@media (max-width: 600px) {
  .sidebar { width: 92vw; max-width: 320px; left: -92vw;}
  .sidebar.active { left: 0; }
  .main { padding: 10px 2vw 16px 2vw;}
  .info-board { padding: 10px 7px; border-radius:10px;}
  .info-board h2 { font-size: 1.1em;}
}
@media (max-width:400px){
  .info-board h2 { font-size: 1em;}
  .info-board { padding: 7px 2px;}
}
.info-board {
  background: #fff;
  border-radius: 16px;
  padding: 26px 30px;
  box-shadow: 0 4px 18px #3178c60c, 0 1.5px 8px #a8c8f088;
  border-top: 8px solid #6fa6e3;
}
.info-board h2 {
  text-align: center;
  color: #3178c6;
  margin-bottom: 22px;
  font-weight: 800;
  letter-spacing: 1px;
}
</style>

<!-- Sidebar Toggle Button (hiện trên mobile) -->
<button id="sidebarToggle" aria-label="Mở menu" title="Mở menu" style="display:none;"><i class="bi bi-list"></i></button>
<div class="sidebar" id="sidebar">
  <ul id="Chung">
    <li><a href="dashboard.php"><i class="bi bi-house-door"></i> Trang chủ</a></li>
    <li><a href="modules/events.php"><i class="bi bi-calendar-event"></i> Quản lý sự kiện</a></li>
    <li><a href="modules/students.php"><i class="bi bi-people"></i> Quản lý học sinh</a></li>
    <li><a href="modules/attendance.php"><i class="bi bi-clipboard-check"></i> Điểm danh</a></li>
    <!-- <li><a href="modules/attendancetraisinh.php"><i class="bi bi-clipboar-check"></i> Điểm danh Trại Sinh</a></li> -->
    <li>
      <a href="#" data-bs-toggle="collapse" class="d-flex align-items-center" 
         role="button" aria-expanded="false" aria-controls="utilitiesMenu1" data-bs-target="#utilitiesMenu1" onclick="event.preventDefault()">
        <i class="bi bi-grid"></i>
        <span style="flex: 1;">Điểm danh Trại sinh</span>
        <i class="bi bi-chevron-down ms-auto"></i>
      </a>
      <ul class="collapse list-unstyled ps-4" id="utilitiesMenu1" data-bs-parent="#Chung">
        <li>
          <a href="attendanceTraiSinh/views/create_pin.php">
            <i class="bi bi-people-fill"></i> Tạo mã PIN (ADMIN)
          </a>
        </li>
        <li>
          <a href="attendanceTraiSinh/views/enter_pin.php">
            <i class="bi bi-people-fill"></i> Điểm danh (BTC)
          </a>
        </li>
        <li>
          <a href="attendanceTraiSinh/views/attendance_list.php">
            <i class="bi bi-people-fill"></i> Kiểm tra điểm danh
          </a>
        </li>
        <li>
          <a href="attendanceTraiSinh/modules/manage_campers.php">
            <i class="bi bi-people-fill"></i> Xóa, sửa, thêm trại sinh
          </a>
        </li>
      </ul>
    </li>
    <li>
      <a href="#" data-bs-toggle="collapse" class="d-flex align-items-center" 
         role="button" aria-expanded="false" aria-controls="utilitiesMenu2" data-bs-target="#utilitiesMenu2" onclick="event.preventDefault()">
        <i class="bi bi-grid"></i>
        <span style="flex: 1;">Tiện ích</span>
        <i class="bi bi-chevron-down ms-auto"></i>
      </a>
      <ul class="collapse list-unstyled ps-4" id="utilitiesMenu2" data-bs-parent="#Chung">
        <li>
          <a href="modules/team.php">
            <i class="bi bi-people-fill"></i> Đội
          </a>
        </li>
        <li>
          <a href="https://www.online-stopwatch.com/">
            <i class="bi bi-joystick"></i> Trò chơi
          </a>
        </li>
      </ul>
    </li>
    <li><a href="modules/report.php"><i class="bi bi-bar-chart-line"></i> Thống kê</a></li>
    <li><a href="modules/users.php"><i class="bi bi-person-gear"></i> Quản lý tài khoản</a></li>
    <li><a href="#"><i class="bi bi-bar-chart"></i> Khảo sát</a></li>
    <li>
      <a href="#" class="nav-link" data-bs-toggle="modal" data-bs-target="#softInfoModal">
        <i class="bi bi-info-circle"></i> Thông tin phần mềm
      </a>
    </li>
    <li><a href="#"><i class="bi bi-question-circle"></i> Hướng dẫn</a></li>
    <li><a href="logout.php"><i class="bi bi-box-arrow-right"></i> Đăng xuất</a></li>
  </ul>
</div>
<div class="sidebar-backdrop" id="sidebarBackdrop" style="display:none;"></div>
<div class="main">
  <div class="info-board">
    <h2>BẢNG THÔNG TIN</h2>
    <div style="color:#3178c6;font-weight:600;margin-bottom:8px;">
      HIỆN TẠI: NĂM HỌC 2025 – 2026
    </div>
    <div style="border:1.5px dashed #6fa6e3;border-radius:7px;padding:13px 16px;margin-bottom:18px;">
      <span style="color:#e72c2c;font-weight:600;">THÔNG BÁO QUAN TRỌNG</span><br>
      <span style="color:#222;">
        Đây là <b>phiên bản thử nghiệm BETA 01</b>. Đội ngũ 
        <b>CLB KỸ NĂNG ĐOÀN – HỘI TRƯỜNG THPT LÝ THƯỜNG KIỆT</b> 
        đang trong quá trình phát triển, vì vậy có thể vẫn còn một số thiếu sót và lỗi nhỏ. 
        Rất mong quý thầy cô và các bạn thông cảm và đóng góp ý kiến để chúng em hoàn thiện hơn!
      </span>
    </div>
    <div style="border:1.5px dashed #6fa6e3;border-radius:7px;padding:13px 16px;">
      <span style="color:#e72c2c;font-weight:600;">LỜI CẢM ƠN</span><br>
      <span style="color:#222;">
        CLB KỸ NĂNG ĐOÀN – HỘI xin gửi lời cảm ơn chân thành đến quý thầy cô và các bạn đã tin tưởng 
        và trải nghiệm phiên bản thử nghiệm này. Sự đồng hành và đóng góp ý kiến từ các bạn 
        chính là nguồn động lực lớn để đội ngũ tiếp tục cải tiến và mang đến những trải nghiệm tốt hơn.  
        <br><br>
        <i>💡 Mỗi góp ý của bạn là một bước tiến để chúng ta cùng nhau xây dựng một nền tảng hoàn thiện hơn!</i>
      </span>
    </div>
  </div>
  <!-- Modal Thông tin phần mềm -->
  <div class="modal fade" id="softInfoModal" tabindex="-1" aria-labelledby="softInfoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header bg-light">
          <h5 class="modal-title" id="softInfoModalLabel"><i class="bi bi-info-circle"></i> Thông tin phần mềm</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
        </div>
        <div class="modal-body">
          <table class="table table-bordered mb-0">
              <tr>
                  <th style="width:240px;">Tên</th>
                  <td>Hệ thống quản lý thành viên và sự kiện</td>
              </tr>
              <tr>
                  <th>Đơn vị xử lý sự cố phần mềm</th>
                  <td>Cội nguồn CLB (Email: <a href="mailto:tophanmem@tdtu.edu.vn">clbkynangdoan.ltk@gmail.com</a>)</td>
              </tr>
              <tr>
                  <th>Fan Page</th>
                  <td><a href="https://www.facebook.com/clbkynangdoan.ltk" target="_blank">https://www.facebook.com/clbkynangdoan.ltk</a></td>
              </tr>
              <tr>
                  <th>Đơn vị hỗ trợ thông tin</th>
                  <td>CLB Kỹ năng Đoàn - Hội Trường THPT Lý Thường Kiệt</td>
              </tr>
              <tr>
                  <th>Trình duyệt hỗ trợ tốt nhất</th>
                  <td>
                      <span style="font-size:1.5em;">🦊</span>
                      <span style="font-size:1.5em;">🌐</span>
                  </td>
              </tr>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Sidebar toggle logic for mobile
const sidebar = document.getElementById('sidebar');
const sidebarToggle = document.getElementById('sidebarToggle');
const sidebarBackdrop = document.getElementById('sidebarBackdrop');
function openSidebar() {
  sidebar.classList.add('active');
  sidebarBackdrop.style.display = 'block';
  document.body.classList.add('sidebar-open');
}
function closeSidebar() {
  sidebar.classList.remove('active');
  sidebarBackdrop.style.display = 'none';
  document.body.classList.remove('sidebar-open');
  // Đóng menu tiện ích nếu đang mở
  const utilitiesMenu = document.getElementById('utilitiesMenu');
  if (utilitiesMenu && utilitiesMenu.classList.contains('show')) {
    const collapseInstance = bootstrap.Collapse.getOrCreateInstance(utilitiesMenu);
    collapseInstance.hide();
  }
}
sidebarToggle.addEventListener('click', openSidebar);
sidebarBackdrop.addEventListener('click', closeSidebar);
window.addEventListener('resize', function() {
  if (window.innerWidth > 900) {
    closeSidebar();
  }
});

</script>
</body>
</html>