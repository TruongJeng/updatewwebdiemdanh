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
  /* ===== MAIN CONTENT ===== */
.main {
  margin-left: 230px;
  margin-top: 60px; /* né header */
  padding: 32px 36px 28px 36px;
  min-height: calc(100vh - 60px);
}


  /* Khung nền */
  background: linear-gradient(180deg, #f6faff, #eef5ff);
  border-radius: 22px 0 0 22px;
  box-shadow:
    inset 0 0 0 1.5px #cfe0f5,
    0 6px 24px rgba(49,120,198,0.08);

  transition: margin-left .25s ease, padding .25s ease;
}

/* Mobile */
@media (max-width: 900px) {
  .main {
    margin-left: 0;
    padding: 18px 5vw 26px 5vw;
    border-radius: 0;
    box-shadow: none;
    background: #f6faff;
  }
}

</style>
<?php include 'includes/sidebar.php'; ?>
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>