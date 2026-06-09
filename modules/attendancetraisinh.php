<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Điểm danh sự kiện</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Tailwind CSS (CDN) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { primary: { 50: '#eff6ff', 100: '#dbeafe', 200: '#bfdbfe', 300: '#93c5fd', 400: '#60a5fa', 500: '#3b82f6', 600: '#2563eb', 700: '#1d4ed8', 800: '#1e40af', 900: '#1e3a8a' } },
                    animation: { 'pulse-slow': 'pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite' }
                }
            }
        }
    </script>
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); }
        #reader { border-radius: 1rem; overflow: hidden; }
        #reader video { object-fit: cover; border-radius: 1rem; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4 sm:p-6 text-slate-800 font-sans antialiased bg-[url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI0MCIgaGVpZ2h0PSI0MCI+CjxwYXRoIGQ9Ik0wIDBoNDB2NDB2NDBIMHoiIGZpbGw9IiNmOGZhZmMiLz4KPHBhdGggZD0iTTAgMGg0MHY0MEgweiIgZmlsbD0ibm9uZSIgc3Ryb2tlPSIjZTJlNThjIiBzdHJva2Utd2lkdGg9IjEiIG9wYWNpdHk9IjAuMSIvPgo8L3N2Zz4=')]">

    <div class="w-full max-w-md bg-white/80 backdrop-blur-xl rounded-3xl shadow-[0_8px_30px_rgb(0,0,0,0.08)] border border-white p-6 sm:p-8 animate-[fadeInUp_0.5s_ease-out]">
        
        <div class="text-center mb-6">
            <div class="w-16 h-16 bg-primary-100 text-primary-600 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-inner rotate-3 transition-transform hover:rotate-0">
                <i class="bi bi-qr-code-scan text-3xl"></i>
            </div>
            <h2 class="text-2xl font-black text-slate-800 tracking-tight">Điểm danh Check-in</h2>
            <p class="text-slate-500 text-sm font-medium mt-1">Quét mã QR để xác nhận tham gia sự kiện</p>
        </div>

        <!-- Dropdown chọn sự kiện -->
        <div class="mb-6">
            <label for="eventDropdown" class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Chọn sự kiện</label>
            <div class="relative">
                <select id="eventDropdown" class="w-full appearance-none bg-slate-50 border-2 border-slate-200 text-slate-700 font-bold rounded-xl px-4 py-3 pr-10 focus:border-primary-500 focus:bg-white focus:ring-4 focus:ring-primary-500/20 outline-none transition-all cursor-pointer shadow-sm">
                    <option value="" disabled selected>Đang tải danh sách...</option>
                </select>
                <i class="bi bi-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none font-bold"></i>
            </div>
        </div>

        <!-- Vùng quét QR -->
        <div id="readerWrapper" class="hidden mb-6 relative">
            <div class="absolute inset-0 border-4 border-primary-500/30 rounded-2xl animate-pulse-slow pointer-events-none z-10"></div>
            <div id="reader" class="w-full shadow-inner bg-black"></div>
        </div>

        <!-- Nút Bắt đầu & Dừng quét -->
        <div class="flex justify-center gap-3 mb-2">
            <button id="startQRScanner" class="w-full bg-gradient-to-r from-primary-600 to-primary-500 hover:from-primary-700 hover:to-primary-600 text-white font-bold py-3.5 px-6 rounded-xl shadow-md hover:shadow-lg transition-all transform hover:-translate-y-0.5 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none flex items-center justify-center gap-2" disabled>
                <i class="bi bi-camera-fill text-lg"></i> Bắt đầu quét QR
            </button>
            <button id="stopQRScanner" class="hidden w-full bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold py-3.5 px-6 rounded-xl shadow-sm transition-all flex items-center justify-center gap-2 border border-slate-200">
                <i class="bi bi-stop-circle-fill text-red-500 text-lg"></i> Dừng quét
            </button>
        </div>

        <!-- Hiển thị thông tin học sinh -->
        <div id="checkInDetails" class="hidden mt-6 animate-[fadeIn_0.3s_ease-out]">
            <div class="relative bg-emerald-50 rounded-2xl p-6 border border-emerald-100 text-center shadow-inner overflow-hidden">
                <!-- Background decoration -->
                <div class="absolute -right-6 -top-6 w-24 h-24 bg-emerald-500/10 rounded-full blur-2xl"></div>
                
                <h5 class="text-emerald-700 font-extrabold text-sm uppercase tracking-wider mb-4 flex items-center justify-center gap-2">
                    <i class="bi bi-check-circle-fill"></i> Thành công
                </h5>
                
                <div class="relative inline-block mb-3">
                    <img id="studentPhoto" class="w-24 h-24 sm:w-28 sm:h-28 rounded-full object-cover border-4 border-white shadow-md bg-white" src="" alt="Ảnh học sinh">
                    <div class="absolute bottom-0 right-0 w-6 h-6 bg-emerald-500 border-2 border-white rounded-full flex items-center justify-center text-white shadow-sm">
                        <i class="bi bi-check text-xs font-bold"></i>
                    </div>
                </div>
                
                <h3 id="userFullName" class="text-xl font-black text-slate-800 mb-1"></h3>
                <div class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-white text-slate-600 shadow-sm border border-slate-100 mb-3">
                    Lớp <span id="userClass" class="ml-1"></span>
                </div>
                
                <p class="text-sm font-medium text-slate-600">
                    Trạng thái: <span id="userAction" class="font-bold text-emerald-600"></span>
                </p>
                <p id="attendanceMessage" class="text-xs text-slate-500 font-medium mt-2 bg-white/60 p-2 rounded-lg"></p>
            </div>
        </div>
    </div>

    <!-- Script HTML5Qrcode -->
    <script src="/hethongdiemdanh/assets/js/html5-qrcode-master/minified/html5-qrcode.min.js"></script>
    <script>
        const eventDropdown = document.getElementById('eventDropdown');
        const startQRScannerBtn = document.getElementById('startQRScanner');
        const stopQRScannerBtn = document.getElementById('stopQRScanner');
        const readerWrapper = document.getElementById('readerWrapper');
        const checkInDetails = document.getElementById('checkInDetails');
        
        const qrScanner = new Html5Qrcode("reader");

        // Lấy danh sách sự kiện từ API events.php
        fetch('/hethongdiemdanh/modules/events.php?format=json')
            .then(response => {
                if (!response.ok) throw new Error("Không thể tải danh sách sự kiện.");
                return response.json();
            })
            .then(data => {
                eventDropdown.innerHTML = '<option value="" disabled selected>-- Chọn sự kiện để quét --</option>';
                if (data.success && data.events.length > 0) {
                    data.events.forEach(event => {
                        const option = document.createElement('option');
                        option.value = event.id;
                        option.textContent = `${event.title} (${event.event_date})`;
                        eventDropdown.appendChild(option);
                    });
                } else {
                    eventDropdown.innerHTML = '<option value="" disabled selected>Không có sự kiện nào</option>';
                }
            })
            .catch(error => {
                console.error('Lỗi khi tải danh sách sự kiện:', error);
                eventDropdown.innerHTML = '<option value="" disabled selected>Lỗi tải sự kiện</option>';
            });

        eventDropdown.addEventListener('change', () => {
            startQRScannerBtn.disabled = false;
        });

        // Start scanning
        startQRScannerBtn.addEventListener('click', () => {
            const eventId = eventDropdown.value;
            if (!eventId) {
                alert("Vui lòng chọn một sự kiện!");
                return;
            }

            // Hide previous results
            checkInDetails.classList.add('hidden');
            
            // Show scanner UI
            readerWrapper.classList.remove('hidden');
            startQRScannerBtn.classList.add('hidden');
            stopQRScannerBtn.classList.remove('hidden');

            qrScanner.start(
                { facingMode: "environment" },
                { fps: 10, qrbox: { width: 250, height: 250 } },
                async (decodedText) => {
                    // Tạm dừng quét trong khi xử lý
                    qrScanner.pause();
                    
                    try {
                        const response = await fetch(`/hethongdiemdanh/modules/process_qr.php?qr_code=${encodeURIComponent(decodedText)}&event_id=${encodeURIComponent(eventId)}`);
                        const data = await response.json();

                        if (data.success) {
                            document.getElementById('userFullName').textContent = data.full_name;
                            document.getElementById('userClass').textContent = data.class;
                            document.getElementById('userAction').textContent = data.action;
                            document.getElementById('attendanceMessage').textContent = data.message;
                            document.getElementById('studentPhoto').src = data.profile_photo || '/hethongdiemdanh/assets/default_avatar.png';

                            checkInDetails.classList.remove('hidden');
                            
                            // Ẩn máy quét và hiện nút
                            qrScanner.stop().then(() => {
                                readerWrapper.classList.add('hidden');
                                startQRScannerBtn.classList.remove('hidden');
                                stopQRScannerBtn.classList.add('hidden');
                            });
                        } else {
                            alert(data.error || "Không thể xử lý mã QR.");
                            qrScanner.resume();
                        }
                    } catch(err) {
                        alert("Lỗi kết nối máy chủ!");
                        qrScanner.resume();
                    }
                },
                (error) => {
                    // ignore scan errors
                }
            ).catch(err => {
                alert("Lỗi khởi động camera: " + err);
                readerWrapper.classList.add('hidden');
                startQRScannerBtn.classList.remove('hidden');
                stopQRScannerBtn.classList.add('hidden');
            });
        });

        // Stop scanning
        stopQRScannerBtn.addEventListener('click', () => {
            qrScanner.stop().then(() => {
                readerWrapper.classList.add('hidden');
                startQRScannerBtn.classList.remove('hidden');
                stopQRScannerBtn.classList.add('hidden');
            }).catch(err => {
                console.error("Lỗi khi dừng quét:", err);
            });
        });
    </script>
    <style>
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
    </style>
</body>
</html>