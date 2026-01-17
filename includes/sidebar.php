<?php
// sidebar.php – All in one
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!-- ===== SIDEBAR STYLE ===== -->
<style>
/* Sidebar */
.sidebar {
  position: fixed;
  top: 60px;
  left: 0;
  width: 230px;
  height: calc(100vh - 60px); /* QUAN TRỌNG */
  background: #f4faff;
  border-right: 1.5px solid #a8c8f0;
  padding-top: 16px;
  overflow-y: auto;
  z-index: 1090;
}
.sidebar ul { list-style: none; padding: 0; margin: 0; }
.sidebar li { margin-bottom: 6px; }

.sidebar a {
  display: flex;
  align-items: center;
  padding: 10px 18px;
  color: #3178c6;
  text-decoration: none;
  border-radius: 8px 0 0 8px;
}
.sidebar a:hover,
.sidebar a.active {
  background: #a8c8f0;
  color: #1757a6;
}
.sidebar i { margin-right: 9px; }

/* Toggle button */
#sidebarToggle {
  display: none;
  background: none;
  border: none;
  font-size: 2rem;
  cursor: pointer;
  z-index: 2000;
}

/* Backdrop */
.sidebar-backdrop {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(40,60,110,.18);
  z-index: 1080;
}

/* Mobile */
@media (max-width: 900px) {
  .sidebar {
    top: 0;
    left: -240px;
    border-radius: 0 18px 18px 0;
    box-shadow: 2px 0 12px #0002;
  }
  .sidebar.active { left: 0; }

  #sidebarToggle {
    display: flex;
    position: fixed;
    top: 12px;
    left: 12px;
    color: #fff;
  }

  body.sidebar-open {
    overflow: hidden;
  }
}
/* ===== MAIN CONTENT ===== */
.main {
  margin-left: 230px;
  padding: 40px 32px 24px 32px;
  transition: margin-left .25s ease;
}

/* Mobile */
@media (max-width: 900px) {
  .main {
    margin-left: 0;
    padding: 16px 4vw 24px 4vw;
  }
}

</style>

<<!-- Sidebar Toggle Button (hiện trên mobile) -->
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
                <li>
          <a href="attendanceTraiSinh/modules/chiadoi.php">
            <i class="bi bi-people-fill"></i> Chia đội tự động
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

<!-- ===== SIDEBAR SCRIPT ===== -->
<script>
const sidebar = document.getElementById('sidebar');
const toggle = document.getElementById('sidebarToggle');
const backdrop = document.getElementById('sidebarBackdrop');

toggle.addEventListener('click', () => {
  sidebar.classList.add('active');
  backdrop.style.display = 'block';
  document.body.classList.add('sidebar-open');
});

backdrop.addEventListener('click', () => {
  sidebar.classList.remove('active');
  backdrop.style.display = 'none';
  document.body.classList.remove('sidebar-open');

  // Đóng toàn bộ submenu
  document.querySelectorAll('.collapse.show').forEach(el => {
    bootstrap.Collapse.getOrCreateInstance(el).hide();
  });
});

window.addEventListener('resize', () => {
  if (window.innerWidth > 900) {
    sidebar.classList.remove('active');
    backdrop.style.display = 'none';
    document.body.classList.remove('sidebar-open');
  }
});
</script>
