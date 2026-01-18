<?php
// sidebar.php – FINAL
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!-- ===== SIDEBAR + MAIN STYLE ===== -->
<style>
/* ===== SIDEBAR ===== */
.sidebar {
  position: fixed;
  top: 50px;                 /* dính header */
  left: 0;
  width: 230px;
  height: calc(100vh - 50px);
  background: #f4faff;
  border-right: 1.5px solid #a8c8f0;
  padding-top: 12px;
  overflow-y: auto;
  z-index: 1090;
  transition: left .25s ease;
}

.sidebar ul { list-style: none; padding: 0; margin: 0; }
.sidebar li { margin-bottom: 4px; }

.sidebar a {
  display: flex;
  align-items: center;
  padding: 10px 18px;
  color: #3178c6;
  text-decoration: none;
  border-radius: 8px 0 0 8px;
  font-weight: 500;
}
.sidebar a:hover,
.sidebar a.active {
  background: #a8c8f0;
  color: #1757a6;
}
.sidebar i { margin-right: 10px; }

/* Chevron animation */
.bi-chevron-down {
  transition: transform .2s ease;
}
a[aria-expanded="true"] .bi-chevron-down {
  transform: rotate(180deg);
}

/* ===== TOGGLE BUTTON (MOBILE) ===== */
#sidebarToggle {
  display: none;
  background: none;
  border: none;
  font-size: 2rem;
  cursor: pointer;
  z-index: 2000;
}

/* ===== BACKDROP ===== */
.sidebar-backdrop {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(40,60,110,.18);
  z-index: 1080;
}

/* ===== MAIN CONTENT ===== */
.main {
  margin-left: 230px;
  margin-top: 60px;
  padding: 32px 36px;
  min-height: calc(100vh - 60px);
  transition: margin-left .25s ease;
}

/* ===== MOBILE ===== */
@media (max-width: 900px) {
  .sidebar {
    left: -240px;
    border-radius: 0 18px 18px 0;
    box-shadow: 2px 0 12px rgba(0,0,0,.15);
  }
  .sidebar.active { left: 0; }

  #sidebarToggle {
    display: flex;
    position: fixed;
    top: 12px;
    left: 12px;
    color: #fff;
  }

  .main {
    margin-left: 0;
    margin-top: 60px;
    padding: 16px 4vw 24px 4vw;
  }

  body.sidebar-open {
    overflow: hidden;
  }
}
</style>

<!-- ===== SIDEBAR TOGGLE (MOBILE) ===== -->
<button id="sidebarToggle" aria-label="Mở menu" title="Mở menu">
  <i class="bi bi-list"></i>
</button>

<!-- ===== SIDEBAR ===== -->
<div class="sidebar" id="sidebar">
  <ul id="Chung">

    <li>
      <a href="dashboard.php" class="<?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
        <i class="bi bi-house-door"></i> Trang chủ
      </a>
    </li>

    <li>
      <a href="modules/events.php">
        <i class="bi bi-calendar-event"></i> Quản lý sự kiện
      </a>
    </li>

    <li>
      <a href="modules/students.php">
        <i class="bi bi-people"></i> Quản lý học sinh
      </a>
    </li>

    <li>
      <a href="modules/attendance.php">
        <i class="bi bi-clipboard-check"></i> Điểm danh
      </a>
    </li>

    <!-- ===== ĐIỂM DANH TRẠI SINH ===== -->
    <li>
      <a href="#"
         data-bs-toggle="collapse"
         data-bs-target="#menuTraiSinh"
         aria-expanded="false"
         class="d-flex align-items-center">
        <i class="bi bi-grid"></i>
        <span style="flex:1;">Điểm danh Trại sinh</span>
        <i class="bi bi-chevron-down ms-auto"></i>
      </a>

      <ul class="collapse list-unstyled ps-4" id="menuTraiSinh" data-bs-parent="#Chung">
        <li><a href="attendanceTraiSinh/views/create_pin.php"><i class="bi bi-key"></i> Tạo mã PIN</a></li>
        <li><a href="attendanceTraiSinh/views/enter_pin.php"><i class="bi bi-person-check"></i> Điểm danh</a></li>
        <li><a href="attendanceTraiSinh/views/attendance_list.php"><i class="bi bi-list-check"></i> Kiểm tra</a></li>
        <li><a href="attendanceTraiSinh/modules/manage_campers.php"><i class="bi bi-pencil"></i> Quản lý trại sinh</a></li>
        <li><a href="attendanceTraiSinh/modules/chiadoi.php"><i class="bi bi-diagram-3"></i> Chia đội</a></li>
      </ul>
    </li>

    <!-- ===== TIỆN ÍCH ===== -->
    <li>
      <a href="#"
         data-bs-toggle="collapse"
         data-bs-target="#menuUtilities"
         aria-expanded="false"
         class="d-flex align-items-center">
        <i class="bi bi-grid"></i>
        <span style="flex:1;">Tiện ích</span>
        <i class="bi bi-chevron-down ms-auto"></i>
      </a>

      <ul class="collapse list-unstyled ps-4" id="menuUtilities" data-bs-parent="#Chung">
        <li><a href="modules/team.php"><i class="bi bi-people-fill"></i> Đội</a></li>
        <li>
          <a href="https://www.online-stopwatch.com/" target="_blank" rel="noopener noreferrer">
            <i class="bi bi-stopwatch"></i> Trò chơi
          </a>
        </li>
      </ul>
    </li>

    <li>
      <a href="modules/report.php">
        <i class="bi bi-bar-chart-line"></i> Thống kê
      </a>
    </li>

    <li>
      <a href="modules/users.php">
        <i class="bi bi-person-gear"></i> Quản lý tài khoản
      </a>
    </li>

    <li>
      <a href="#" data-bs-toggle="modal" data-bs-target="#softInfoModal">
        <i class="bi bi-info-circle"></i> Thông tin phần mềm
      </a>
    </li>

    <li>
      <a href="logout.php">
        <i class="bi bi-box-arrow-right"></i> Đăng xuất
      </a>
    </li>

  </ul>
</div>

<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

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
