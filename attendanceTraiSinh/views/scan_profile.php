<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>WELCOME TRẠI SINH</title>

<!-- Tailwind CSS -->
<script src="https://cdn.tailwindcss.com"></script>
<!-- Bootstrap Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<!-- HTML5 QR Code -->
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

<style>
/* Custom Tailwind Configuration */
@tailwind base;
@tailwind components;
@tailwind utilities;

@layer utilities {
    .glass-panel {
        @apply bg-white/10 backdrop-blur-xl border border-white/20 shadow-2xl;
    }
}

body {
    margin: 0;
    min-height: 100vh;
    background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 50%, #3b82f6 100%);
    background-size: 200% 200%;
    animation: gradientBG 15s ease infinite;
    font-family: 'Inter', system-ui, -apple-system, sans-serif;
    color: #fff;
    overflow: hidden;
}

@keyframes gradientBG {
    0% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
}

/* ===== WELCOME ===== */
#welcome {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    animation: fade 0.8s ease-out;
}

.title-glow {
    text-shadow: 0 0 40px rgba(59, 130, 246, 0.6);
}

/* ===== PROFILE ===== */
#result {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    pointer-events: none;
    z-index: 50;
}

.profile-card {
    pointer-events: auto;
    animation: popIn 0.5s cubic-bezier(0.16, 1, 0.3, 1);
}

/* ===== FIREWORK ===== */
canvas {
    position: absolute;
    inset: 0;
    pointer-events: none;
    z-index: 40;
}

/* Customizing QR Scanner */
#reader {
    background: rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
    border-radius: 1.5rem !important;
    overflow: hidden;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
}
#reader video {
    border-radius: 1.5rem !important;
}
#reader__dashboard_section_csr span { display: none !important; }
#reader__dashboard_section_csr button {
    background: #3b82f6 !important;
    color: white !important;
    border: none !important;
    padding: 0.5rem 1rem !important;
    border-radius: 0.5rem !important;
    font-weight: 600 !important;
    margin-top: 0.5rem !important;
}

@keyframes fade {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}
@keyframes popIn {
    0% { transform: scale(0.8) translateY(30px); opacity: 0; }
    50% { transform: scale(1.05) translateY(-10px); opacity: 1; }
    100% { transform: scale(1) translateY(0); opacity: 1; }
}
@keyframes float {
    0% { transform: translateY(0px); }
    50% { transform: translateY(-10px); }
    100% { transform: translateY(0px); }
}
</style>
</head>

<body>

<!-- 🔊 MUSIC -->
<audio id="bgm" loop>
    <source src="/hethongdiemdanh/assets/welcome.mp3" type="audio/mpeg">
</audio>

<!-- 🎉 WELCOME -->
<div id="welcome">
    <div class="mb-8 animate-[float_4s_ease-in-out_infinite]">
        <img src="/hethongdiemdanh/assets/logo_CLB.png" alt="Logo" class="w-32 h-32 object-contain drop-shadow-[0_0_30px_rgba(255,255,255,0.3)]">
    </div>
    <h1 class="text-6xl md:text-8xl font-black tracking-tight title-glow mb-4">WELCOME</h1>
    <h2 class="text-2xl md:text-4xl font-bold text-blue-200 tracking-wide mb-12">TRẠI HÈ THANH NIÊN 2026</h2>
    
    <div class="glass-panel px-8 py-4 rounded-full flex items-center gap-3 animate-bounce shadow-[0_0_30px_rgba(59,130,246,0.3)]">
        <i class="bi bi-qr-code-scan text-2xl text-blue-300"></i>
        <span class="text-lg font-semibold tracking-wide text-blue-50">Vui lòng đưa mã QR trước camera</span>
    </div>
</div>

<!-- 📷 QR -->
<div class="absolute bottom-10 left-1/2 -translate-x-1/2 w-80 max-w-[90vw] z-30">
    <div id="reader"></div>
</div>

<!-- 🧑 PROFILE -->
<div id="result"></div>

<!-- 🎆 FIREWORK -->
<canvas id="firework"></canvas>

<script>
const welcome = document.getElementById('welcome');
const resultBox = document.getElementById('result');
const music = document.getElementById('bgm');

/* ===== MUSIC AUTO START ===== */
document.body.addEventListener('click', () => {
    music.play().catch(()=>{});
}, {once: true});

/* ===== QR ===== */
const scanner = new Html5QrcodeScanner(
    "reader",
    { fps: 10, qrbox: { width: 250, height: 250 } },
    false
);

function onScanSuccess(text){
    scanner.clear();
    welcome.style.opacity = '0';
    setTimeout(() => welcome.style.display = 'none', 300);

    fetch('../api/get_camper_by_qr.php',{
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({student_code: text.trim()})
    })
    .then(r => r.json())
    .then(res => {
        if(!res.success){
            alert(res.message);
            restart();
            return;
        }
        showProfile(res.student);
        firework();
        setTimeout(restart, 6000);
    })
    .catch(() => {
        alert('Có lỗi xảy ra, vui lòng thử lại.');
        restart();
    });
}

function showProfile(s){
    resultBox.innerHTML = `
        <div class="profile-card glass-panel w-96 max-w-[90vw] p-8 rounded-[2rem] flex flex-col items-center relative overflow-hidden group">
            <div class="absolute inset-0 bg-gradient-to-br from-white/20 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
            
            <div class="relative w-40 h-40 mb-6">
                <div class="absolute inset-0 bg-blue-500 rounded-full animate-ping opacity-20"></div>
                <img src="${s.avatar || '/hethongdiemdanh/assets/default.png'}" 
                     onerror="this.src='/hethongdiemdanh/assets/default.png'"
                     class="w-full h-full rounded-full object-cover border-4 border-white/50 shadow-2xl relative z-10">
            </div>
            
            <h2 class="text-3xl font-black text-center mb-2 tracking-tight">${s.name}</h2>
            
            <div class="flex items-center justify-center gap-4 w-full mt-4">
                <div class="bg-white/10 px-4 py-2 rounded-xl border border-white/10 flex-1">
                    <div class="text-xs text-blue-200 uppercase font-bold tracking-wider mb-1">Lớp</div>
                    <div class="font-bold text-lg">${s.class}</div>
                </div>
                <div class="bg-white/10 px-4 py-2 rounded-xl border border-white/10 flex-1">
                    <div class="text-xs text-blue-200 uppercase font-bold tracking-wider mb-1">Mã Trại Sinh</div>
                    <div class="font-bold text-lg font-mono">${s.code}</div>
                </div>
            </div>
            
            <div class="mt-6 inline-flex items-center justify-center bg-emerald-500/20 border border-emerald-500/50 text-emerald-300 px-6 py-2 rounded-full font-bold shadow-[0_0_15px_rgba(16,185,129,0.2)]">
                <i class="bi bi-check-circle-fill mr-2"></i> Xác nhận thành công
            </div>
        </div>
    `;
}

function restart(){
    resultBox.innerHTML = '';
    welcome.style.display = 'flex';
    setTimeout(() => welcome.style.opacity = '1', 50);
    scanner.render(onScanSuccess);
}

scanner.render(onScanSuccess);

/* ===== FIREWORK EFFECT ===== */
const canvas = document.getElementById('firework');
const ctx = canvas.getContext('2d');
canvas.width = innerWidth;
canvas.height = innerHeight;

window.addEventListener('resize', () => {
    canvas.width = innerWidth;
    canvas.height = innerHeight;
});

function firework(){
    let particles = [];
    const colors = ['#fde047', '#3b82f6', '#ef4444', '#10b981', '#a855f7', '#ffffff'];
    
    // Create multiple bursts
    for(let b=0; b<3; b++) {
        const cx = canvas.width/2 + (Math.random()-0.5)*300;
        const cy = canvas.height/2 + (Math.random()-0.5)*300;
        
        for(let i=0; i<80; i++){
            const angle = Math.random() * Math.PI * 2;
            const speed = Math.random() * 8 + 2;
            particles.push({
                x: cx,
                y: cy,
                vx: Math.cos(angle) * speed,
                vy: Math.sin(angle) * speed,
                life: Math.random() * 40 + 40,
                maxLife: 80,
                color: colors[Math.floor(Math.random() * colors.length)],
                size: Math.random() * 3 + 1
            });
        }
    }
    
    function draw(){
        ctx.fillStyle = 'rgba(15, 23, 42, 0.2)'; // slight trail effect
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        
        particles.forEach(p => {
            ctx.fillStyle = p.color;
            ctx.globalAlpha = p.life / p.maxLife;
            ctx.beginPath();
            ctx.arc(p.x, p.y, p.size, 0, Math.PI*2);
            ctx.fill();
            
            p.x += p.vx;
            p.y += p.vy;
            p.vy += 0.15; // gravity
            p.life--;
        });
        
        ctx.globalAlpha = 1;
        particles = particles.filter(p => p.life > 0);
        
        if(particles.length > 0) {
            requestAnimationFrame(draw);
        } else {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
        }
    }
    draw();
}
</script>

</body>
</html>
