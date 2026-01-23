<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>WELCOME TRẠI SINH</title>

<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

<style>
body{
    margin:0;
    min-height:100vh;
    background:linear-gradient(135deg,#1565c0,#64b5f6);
    font-family:system-ui;
    color:#fff;
    overflow:hidden;
}

/* ===== WELCOME ===== */
#welcome{
    position:absolute;
    inset:0;
    display:flex;
    align-items:center;
    justify-content:center;
    text-align:center;
    flex-direction:column;
    animation:fade .6s ease;
}

.title{
    font-size:38px;
    font-weight:900;
    letter-spacing:2px;
}

.camp-name{
    font-size:20px;
    margin-top:10px;
    opacity:.9;
}

.hint{
    margin-top:30px;
    padding:16px 26px;
    border-radius:18px;
    background:rgba(255,255,255,.15);
    animation:pulse 1.6s infinite;
}

/* ===== PROFILE ===== */
#result{
    position:absolute;
    inset:0;
    display:flex;
    align-items:center;
    justify-content:center;
}

.card{
    background:#fff;
    color:#000;
    padding:30px;
    border-radius:24px;
    width:360px;
    text-align:center;
    animation:pop .3s ease;
}

.avatar{
    width:150px;
    height:150px;
    border-radius:50%;
    object-fit:cover;
    border:6px solid #1565c0;
}

.name{
    font-size:24px;
    font-weight:800;
    margin-top:10px;
}

.class{color:#555;margin-top:4px}
.code{color:#777;font-size:14px;margin-top:6px}

/* ===== FIREWORK ===== */
canvas{
    position:absolute;
    inset:0;
    pointer-events:none;
}

@keyframes fade{
    from{opacity:0}
    to{opacity:1}
}
@keyframes pop{
    from{transform:scale(.9);opacity:0}
    to{transform:scale(1);opacity:1}
}
@keyframes pulse{
    0%{transform:scale(1)}
    50%{transform:scale(1.05)}
    100%{transform:scale(1)}
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
    <div class="title">WELCOME</div>
    <div class="camp-name">TRẠI HÈ THANH NIÊN 2026</div>
    <div class="hint">📷 Vui lòng đưa mã QR trước camera</div>
</div>

<!-- 📷 QR -->
<div id="reader" style="width:300px;position:absolute;bottom:20px;left:50%;transform:translateX(-50%)"></div>

<!-- 🧑 PROFILE -->
<div id="result"></div>

<!-- 🎆 FIREWORK -->
<canvas id="firework"></canvas>

<script>
const welcome = document.getElementById('welcome');
const resultBox = document.getElementById('result');
const music = document.getElementById('bgm');

/* ===== MUSIC AUTO START ===== */
document.body.addEventListener('click',()=>{
    music.play().catch(()=>{});
},{once:true});

/* ===== QR ===== */
const scanner = new Html5QrcodeScanner(
    "reader",
    { fps:10, qrbox:250 },
    false
);

function onScanSuccess(text){
    scanner.clear();
    welcome.style.display='none';

    fetch('../api/get_camper_by_qr.php',{
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify({student_code:text.trim()})
    })
    .then(r=>r.json())
    .then(res=>{
        if(!res.success){
            alert(res.message);
            restart();
            return;
        }
        showProfile(res.student);
        firework();
        setTimeout(restart,5000);
    });
}

function showProfile(s){
    resultBox.innerHTML=`
        <div class="card">
            <img src="${s.avatar}" class="avatar">
            <div class="name">${s.name}</div>
            <div class="class">Lớp ${s.class}</div>
            <div class="code">Mã ${s.code}</div>
        </div>
    `;
}

function restart(){
    resultBox.innerHTML='';
    welcome.style.display='flex';
    scanner.render(onScanSuccess);
}

scanner.render(onScanSuccess);

/* ===== FIREWORK EFFECT ===== */
const canvas = document.getElementById('firework');
const ctx = canvas.getContext('2d');
canvas.width = innerWidth;
canvas.height = innerHeight;

function firework(){
    let particles=[];
    for(let i=0;i<120;i++){
        particles.push({
            x:canvas.width/2,
            y:canvas.height/2,
            vx:(Math.random()-0.5)*8,
            vy:(Math.random()-0.5)*8,
            life:60
        });
    }
    function draw(){
        ctx.clearRect(0,0,canvas.width,canvas.height);
        particles.forEach(p=>{
            ctx.fillStyle='rgba(255,215,0,.8)';
            ctx.fillRect(p.x,p.y,4,4);
            p.x+=p.vx;
            p.y+=p.vy;
            p.life--;
        });
        particles = particles.filter(p=>p.life>0);
        if(particles.length) requestAnimationFrame(draw);
    }
    draw();
}
</script>

</body>
</html>
