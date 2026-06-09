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

$pageTitle = "Điểm danh bằng QR Code";
$full_name = $_SESSION['full_name'] ?? '';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<!-- UNLOCK AUDIO OVERLAY -->
<div id="unlockAudio" class="fixed inset-0 bg-slate-900/80 backdrop-blur-sm z-[9999] flex items-center justify-center text-white cursor-pointer transition-opacity duration-300">
    <div class="text-center bg-white/10 p-8 rounded-3xl border border-white/20 shadow-2xl backdrop-blur-md animate-[popIn_0.4s_ease-out]">
        <div class="w-20 h-20 bg-primary-500 rounded-full flex items-center justify-center mx-auto mb-6 shadow-lg shadow-primary-500/50 animate-bounce">
            <i class="bi bi-hand-index-thumb-fill text-4xl"></i>
        </div>
        <h3 class="text-2xl font-black tracking-tight mb-2">Chạm để bắt đầu</h3>
        <p class="text-white/70 font-medium">Kích hoạt âm thanh & rung khi quét</p>
    </div>
</div>

<main class="ml-0 lg:ml-64 pt-4 min-h-screen bg-slate-50/50 flex items-center justify-center p-4 sm:p-6 transition-all duration-300 ease-in-out">
    <div class="w-full max-w-md">
        
        <div class="text-center mb-6">
            <div class="inline-flex items-center justify-center w-12 h-12 bg-primary-100 text-primary-600 rounded-xl shadow-sm mb-3">
                <i class="bi bi-qr-code-scan text-2xl"></i>
            </div>
            <h2 class="text-2xl font-extrabold text-slate-800 tracking-tight">QUÉT MÃ QR</h2>
            <p class="text-sm font-medium text-slate-500 mt-1">Đưa mã QR của trại sinh vào khung camera</p>
        </div>

        <div class="bg-white rounded-3xl shadow-[0_20px_60px_-15px_rgba(0,0,0,0.1)] border border-slate-100 overflow-hidden relative z-10">
            <!-- QR Scanner UI Customization via CSS -->
            <div id="reader" class="w-full border-0"></div>
            
            <div class="p-4 bg-slate-50 border-t border-slate-100 text-center">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Trạng thái máy quét</p>
            </div>
        </div>

        <div id="result" class="mt-6 min-h-[120px]"></div>

    </div>
</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

<style>
/* Reset Html5Qrcode styles */
#reader { border: none !important; }
#reader__dashboard_section_csr span { display: none !important; }
#reader__dashboard_section_csr button {
    background: #3178c6 !important;
    color: white !important;
    border: none !important;
    padding: 10px 20px !important;
    border-radius: 12px !important;
    font-weight: 600 !important;
    margin-top: 10px !important;
    box-shadow: 0 4px 14px rgba(49, 120, 198, 0.3) !important;
}
#reader video {
    border-radius: 1.5rem 1.5rem 0 0 !important;
    object-fit: cover;
}

/* Animations & Results */
@keyframes popIn {
  from { transform: scale(0.95) translateY(10px); opacity: 0; }
  to { transform: scale(1) translateY(0); opacity: 1; }
}

.scan-result-card {
    background: white;
    border-radius: 1.5rem;
    padding: 1.5rem;
    text-align: center;
    box-shadow: 0 10px 40px -10px rgba(0,0,0,0.1);
    animation: popIn 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    position: relative;
    overflow: hidden;
}

.scan-result-card.in {
    border: 2px solid #10b981;
}

.scan-result-card.out {
    border: 2px solid #ef4444;
}

.scan-result-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 6px;
}
.scan-result-card.in::before { background: #10b981; }
.scan-result-card.out::before { background: #ef4444; }

.result-avatar {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    object-fit: cover;
    margin: 0 auto 1rem;
    border: 4px solid white;
    box-shadow: 0 8px 24px rgba(0,0,0,0.12);
}
.scan-result-card.in .result-avatar { border-color: #d1fae5; }
.scan-result-card.out .result-avatar { border-color: #fee2e2; }
</style>

<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

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
    const overlay = document.getElementById('unlockAudio');
    overlay.style.opacity = '0';
    setTimeout(() => overlay.remove(), 300);
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
    {fps: 10, qrbox: {width: 250, height: 250}},
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
      <div class="scan-result-card ${isIn ? 'in' : 'out'}">
        <img class="result-avatar"
             src="${data.student.avatar || '/hethongdiemdanh/assets/default.png'}" 
             onerror="this.src='/hethongdiemdanh/assets/default.png'">

        <div class="text-xl font-black text-slate-800 tracking-tight">${data.student.name}</div>
        <div class="text-sm font-medium text-slate-500 mt-1 mb-3">Lớp ${data.student.class}</div>

        <div class="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-full font-bold text-sm ${isIn ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'}">
          <i class="bi ${isIn ? 'bi-box-arrow-in-right' : 'bi-box-arrow-left'}"></i>
          ${isIn ? 'CHECK IN' : 'CHECK OUT'}
        </div>

        <div class="mt-4 text-xs font-semibold text-slate-400 flex justify-center items-center gap-1">
            <i class="bi bi-clock"></i> ${data.time}
        </div>
      </div>
    `;

    setTimeout(() => {
        resultBox.innerHTML = '';
    }, 7000);
}

function showError(msg){
    beep(400,300);
    vibrate([200,100,200]);
    resultBox.innerHTML = `
        <div class="bg-red-50 border border-red-200 text-red-700 p-4 rounded-2xl flex items-start gap-3 shadow-sm animate-[popIn_0.3s_ease-out]">
            <i class="bi bi-exclamation-triangle-fill text-xl mt-0.5"></i>
            <div>
                <h4 class="font-bold text-sm">Quét thất bại</h4>
                <p class="text-sm mt-0.5 opacity-90">${msg}</p>
            </div>
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
