<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/includes/db.php';

/* ===== TIMEOUT ===== */
$timeout = 18000;

/* ===== CHECK LOGIN & TIMEOUT ===== */
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

if (isset($_SESSION['last_active']) && (time() - $_SESSION['last_active'] > $timeout)) {
    session_unset();
    session_destroy();
    header("Location: index.php?timeout=1");
    exit();
}
$_SESSION['last_active'] = time();

/* ===== GET FULL NAME ===== */
if (empty($_SESSION['full_name'])) {
    $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $_SESSION['full_name'] = $stmt->fetchColumn();
}
$full_name = $_SESSION['full_name'];

/* ===== FORCE CHANGE PASSWORD ===== */
if (!empty($_SESSION['first_login'])) {
    header("Location: change_password.php");
    exit();
}

$pageTitle = "CLB Kỹ năng Đoàn - Hội Trường THPT Lý Thường Kiệt";
include __DIR__ . '/includes/header.php';
?>

<style>
/* ===== MAIN ===== */
html, body {
  height: 100%;
  margin: 0;
  overflow: hidden; 
}


/* ===== MAIN ===== */
.main {
  position: fixed;
  top: 0;              /* CHIỀU CAO HEADER */
  left: 0;            /* CHIỀU RỘNG SIDEBAR */
  right: 0;
  bottom: 0;

  padding: 28px 36px;
  overflow-y: auto;       /* CHỈ MAIN ĐƯỢC CUỘN */
  overflow-x: hidden;

  background: linear-gradient(180deg, #f6faff, #eef5ff);
  box-shadow:
    inset 0 0 0 1.5px #cfe0f5,
    0 6px 24px rgba(49,120,198,0.08);
}


/* Mobile */
@media (max-width: 900px) {
  .main {
    margin-left: 0;
    padding: 20px;
    border-radius: 0;
  }
}


/* ===== INFO BOARD ===== */
.info-board { padding: 1px 7px;}


.info-board h2 {
  font-weight: 800;
  margin-bottom: 22px;
  color: #1f3a5f;
}

/* Card */
.info-card {
  background: #fff;
  border-radius: 16px;
  padding: 18px 22px;
  margin-bottom: 18px;
  box-shadow: 0 10px 26px rgba(0,0,0,0.07);
  border-left: 6px solid transparent;
  transition: transform .18s ease, box-shadow .18s ease;
}

.info-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 14px 34px rgba(0,0,0,0.10);
}

.info-card.small {
  padding: 14px 20px;
}

.info-card .title {
  display: flex;
  align-items: center;
  font-weight: 700;
  margin-bottom: 6px;
}

.info-card .icon {
  font-size: 1.3em;
  margin-right: 8px;
}

.info-card p {
  margin: 0;
  line-height: 1.6;
  color: #333;
}

/* Màu */
.card-year {
  border-left-color: #3178c6;
  background: linear-gradient(180deg, #fff, #f3f8ff);
}
.card-warning {
  border-left-color: #e72c2c;
  background: linear-gradient(180deg, #fff, #fff4f4);
}
.card-thanks {
  border-left-color: #2ea44f;
  background: linear-gradient(180deg, #fff, #f4fff8);
}

/* Mobile */
@media (max-width: 900px) {
  .main {
    margin-left: 0;
    padding: 20px;
    border-radius: 0;
  }
}
</style>

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="main">
  <div class="info-board">
    <h2>📌 BẢNG THÔNG TIN</h2>

    <div class="info-card small card-year">
      <div class="title">
        <span class="icon">📅</span> HIỆN TẠI
      </div>
      <p><b>Năm học 2025 – 2026</b></p>
    </div>

    <div class="info-card card-warning">
      <div class="title">
        <span class="icon">⚠️</span> THÔNG BÁO QUAN TRỌNG
      </div>
      <p>
        Đây là <b>phiên bản thử nghiệm BETA 01</b>.  
        CLB Kỹ năng Đoàn – Hội Trường THPT Lý Thường Kiệt đang trong quá trình phát triển,
        nên có thể vẫn còn một số thiếu sót.  
        Rất mong quý thầy cô và các bạn đóng góp ý kiến để hệ thống hoàn thiện hơn.
      </p>
    </div>

    <div class="info-card card-thanks">
      <div class="title">
        <span class="icon">💚</span> LỜI CẢM ƠN
      </div>
      <p>
        CLB Kỹ năng Đoàn – Hội xin chân thành cảm ơn quý thầy cô và các bạn
        đã tin tưởng và trải nghiệm hệ thống.
        <br><br>
        <i>💡 Mỗi góp ý của bạn là một bước tiến để xây dựng nền tảng tốt hơn.</i>
      </p>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
