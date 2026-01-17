<?php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['attendance_session_id'])) {
    header('Location: enter_pin.php');
    exit;
}

$sessionId = $_SESSION['attendance_session_id'];

$stmt = $pdo->prepare("SELECT is_active FROM attendance_sessions WHERE id = ?");
$stmt->execute([$sessionId]);
$s = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$s || $s['is_active'] == 0) {
    header('Location: enter_pin.php?expired=1');
    exit;
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ĐIỂM DANH</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<link rel="icon" type="image/png" href="/hethongdiemdanh/assets/logo_CLB.png">

<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

<style>
:root{
    --primary:#3178c6;
    --bg:#f4faff;
    --card:#ffffff;
    --green:#2ecc71;
    --red:#e74c3c;
}
body{
    background:var(--bg);
    font-family:system-ui,-apple-system,BlinkMacSystemFont;
}
.header{
    background:linear-gradient(90deg,#3178c6,#6fa6e3);
    color:#fff;
    padding:14px 18px;
    font-size:18px;
    font-weight:700;
    display:flex;
    align-items:center;
    gap:10px;
}
.scan-card{
    background:var(--card);
    border-radius:16px;
    padding:16px;
    box-shadow:0 4px 18px #3178c61a;
    max-width:420px;
    margin:auto;
}
.result-card{
    text-align:center;
    margin-top:16px;
}
.result-card img{
    width:200px;
    height:200px;
    object-fit:cover;
    border-radius:12px;
    border:2px solid #eee;
}
.badge-in{ background:#eafaf1; color:var(--green); }
.badge-out{ background:#fdecea; color:var(--red); }
.footer{
    text-align:center;
    font-size:13px;
    color:#666;
    margin-top:20px;
}
.scan-result {
  margin-top: 15px;
  padding: 18px;
  border-radius: 14px;
  text-align: center;
  background: #fff;
  box-shadow: 0 8px 24px rgba(0,0,0,0.12);
  animation: popIn 0.35s ease;
}

.scan-result.in {
  border-left: 6px solid #2ecc71;
}

.scan-result.out {
  border-left: 6px solid #e74c3c;
}

.scan-result .avatar {
  width: 90px;
  height: 90px;
  object-fit: cover;
  border-radius: 50%;
  border: 4px solid #eee;
  margin-bottom: 10px;
}

.scan-result.in .avatar {
  border-color: #2ecc71;
}

.scan-result.out .avatar {
  border-color: #e74c3c;
}

.scan-result .name {
  font-size: 1.25rem;
  font-weight: 700;
}

.scan-result .class {
  color: #666;
  margin-bottom: 8px;
}

.scan-result .status {
  font-weight: 700;
  font-size: 1rem;
  margin: 6px 0;
}

.scan-result .time {
  font-size: 0.9rem;
  color: #555;
}

@keyframes popIn {
  from {
    transform: scale(0.9);
    opacity: 0;
  }
  to {
    transform: scale(1);
    opacity: 1;
  }
}

</style>
</head>

<body>
<?php
$pageTitle = "Scan QR Điểm Danh";
$full_name = $_SESSION['full_name'] ?? '';
include __DIR__ . '/../../includes/header.php';
?>
<!-- UNLOCK AUDIO OVERLAY -->
<div id="unlockAudio"
style="
position:fixed;
inset:0;
background:rgba(0,0,0,.65);
color:#fff;
display:flex;
align-items:center;
justify-content:center;
z-index:9999;
text-align:center;
cursor:pointer;
">
<div>
<i class="bi bi-hand-index-thumb fs-1 mb-3"></i><br>
<b>Chạm màn hình để bắt đầu</b><br>
<small>(Bật âm thanh & rung)</small>
</div>
</div>

<div class="container py-3">
<div class="scan-card">
<div id="reader"></div>
<div id="result" class="result-card"></div>
<div class="text-center text-muted mt-2" style="font-size:13px;">
Đưa mã QR vào khung để quét
</div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

</div>

<script>
/* ===== AUDIO UNLOCK ===== */
let audioCtx = null;
let audioUnlocked = false;
let lastBeep = 0;

function unlockAudio(){
    if(audioUnlocked) return;
    audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    audioCtx.resume();
    if(navigator.vibrate) navigator.vibrate(50);
    audioUnlocked = true;
    document.getElementById('unlockAudio').style.display = 'none';
}
document.getElementById('unlockAudio').addEventListener('click', unlockAudio);

function canBeep(){
    const now = Date.now();
    if(now - lastBeep < 600) return false;
    lastBeep = now;
    return true;
}
function beep(freq=800, duration=120){
    if(!audioUnlocked || !canBeep()) return;
    const osc = audioCtx.createOscillator();
    const gain = audioCtx.createGain();
    osc.connect(gain);
    gain.connect(audioCtx.destination);
    osc.frequency.value = freq;
    gain.gain.value = 0.2;
    osc.start();
    setTimeout(()=>osc.stop(), duration);
}
function vibrate(p){
    if(audioUnlocked && navigator.vibrate) navigator.vibrate(p);
}

/* ===== QR ===== */
const resultBox = document.getElementById('result');

function onScanSuccess(decodedText){
    const code = decodedText.trim();
    if(!/^\d+$/.test(code)){
        showError('QR không hợp lệ');
        return;
    }
    html5QrcodeScanner.clear();
    submitScan(code);
}
function onScanFailure(){}

const html5QrcodeScanner = new Html5QrcodeScanner(
"reader",
{fps:10, qrbox:250},
false
);
html5QrcodeScanner.render(onScanSuccess, onScanFailure);



function submitScan(code){
    fetch('../api/scan.php',{
        method:'POST',
        headers:{
            'Content-Type':'text/plain'
        },
        body: code
    })
    .then(async r => {
    const text = await r.text();
    try {
        return JSON.parse(text);
    } catch(e){
        console.error('Response không phải JSON:', text);
        throw e;
        }
    })
    .then(data=>{
        if(!data.success){
            showError(data.message);
            restartScan();
            return;
        }
        showSuccess(data);
        restartScan(1500);
    })
    .catch(()=>{
        showError('Lỗi server');
        restartScan();
    });
}



/* ===== UI ===== */
function showSuccess(data){
    beep(900,120);
    vibrate(100);

    const isIn = data.type === 'CHECK_IN';

    resultBox.innerHTML = `
      <div class="scan-result ${isIn ? 'in' : 'out'}">
        <img class="avatar"
             src="${data.student.avatar || '/hethongdiemdanh/assets/default.png'}">

        <div class="name">${data.student.name}</div>
        <div class="class">Lớp ${data.student.class}</div>

        <div class="status">
          ${isIn ? ' CHECK IN' : ' CHECK OUT'}
        </div>

        <div class="time">${data.time}</div>
      </div>
    `;

    // tự ẩn sau 7 giây (nếu muốn)
    setTimeout(() => {
        resultBox.innerHTML = '';
    }, 7000);
}

function showError(msg){
    beep(400,300);
    vibrate([200,100,200]);
    resultBox.innerHTML = `
        <div class="alert alert-danger py-2 mb-0">
        <i class="bi bi-exclamation-triangle"></i> ${msg}
        </div>
    `;
}
function restartScan(delay=800){
    setTimeout(()=>{
        resultBox.innerHTML='';
        html5QrcodeScanner.render(onScanSuccess, onScanFailure);
    },delay);
}
</script>

</body>
</html>
