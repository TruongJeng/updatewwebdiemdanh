<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Điểm danh học sinh</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            background: #e8f1fb;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }
        .container-attendance {
            background: #fff;
            max-width: 600px;
            margin: 24px auto;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgb(49 120 198 / 15%);
        }
        .attendance-title {
            color: #3178c6;
            text-align: center;
            margin-bottom: 18px;
            font-weight: 700;
        }
        #reader {
            display: none;
            margin: 12px 0;
        }
        .student-photo {
            max-width: 120px;
            max-height: 120px;
            border-radius: 50%;
            object-fit: cover;
            margin-top: 10px;
        }
        @media (max-width: 768px) {
            .container-attendance {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container-attendance">
        <h2 class="attendance-title"><i class="bi bi-qr-code"></i> Điểm danh bằng mã QR</h2>

        <!-- Dropdown chọn sự kiện -->
        <label for="eventDropdown" class="form-label">Chọn sự kiện:</label>
        <select id="eventDropdown" class="form-control mb-3">
            <option value="" disabled selected>Danh sách sự kiện</option>
        </select>

        <!-- Vùng quét QR -->
        <div id="reader" style="width: 100%;"></div>

        <!-- Nút Bắt đầu & Dừng quét -->
        <div class="text-center mt-3">
            <button id="startQRScanner" class="btn btn-primary" disabled>
                <i class="bi bi-camera"></i> Bắt đầu quét QR
            </button>
            <button id="stopQRScanner" class="btn btn-danger" style="display: none;">
                <i class="bi bi-stop-circle"></i> Dừng quét
            </button>
        </div>

        <!-- Hiển thị thông tin học sinh -->
        <div id="checkInDetails" style="display: none;" class="mt-4 text-center">
            <h5>Kết quả điểm danh:</h5>
            <img id="studentPhoto" class="student-photo" src="" alt="Ảnh học sinh">
            <p><strong>Họ và tên:</strong> <span id="userFullName"></span></p>
            <p><strong>Lớp:</strong> <span id="userClass"></span></p>
            <p><strong>Trạng thái điểm danh:</strong> <span id="userAction"></span></p>
            <p id="attendanceMessage" class="text-muted mt-3"></p>
        </div>
    </div>

    <!-- Tải thư viện Html5Qrcode -->
    <script src="/hethongdiemdanh/assets/js/html5-qrcode-master/minified/html5-qrcode.min.js"></script>
    <script>
        const eventDropdown = document.getElementById('eventDropdown');
        const startQRScannerBtn = document.getElementById('startQRScanner');
        const stopQRScannerBtn = document.getElementById('stopQRScanner');
        const qrScanner = new Html5Qrcode("reader");

        // Lấy danh sách sự kiện từ API events.php
        fetch('/hethongdiemdanh/modules/events.php?format=json')
            .then(response => {
                if (!response.ok) {
                    throw new Error("Không thể tải danh sách sự kiện.");
                }
                return response.json();
            })
            .then(data => {
                if (data.success && data.events.length > 0) {
                    // Thêm sự kiện vào dropdown
                    data.events.forEach(event => {
                        const option = document.createElement('option');
                        option.value = event.id; // Lấy ID sự kiện
                        option.textContent = `${event.title} (Mã PIN: ${event.pin}, Ngày: ${event.event_date})`;
                        eventDropdown.appendChild(option);
                    });
                } else {
                    alert(data.error || "Hiện tại không có sự kiện nào khả dụng.");
                }
            })
            .catch(error => {
                console.error('Lỗi khi tải danh sách sự kiện:', error);
                alert("Lỗi khi tải sự kiện. Vui lòng thử lại sau!");
            });

        // Khi chọn sự kiện trong dropdown
        eventDropdown.addEventListener('change', () => {
            startQRScannerBtn.disabled = false;
        });

        // Bắt đầu quét mã QR
        startQRScannerBtn.addEventListener('click', () => {
            const eventId = eventDropdown.value;
            if (!eventId) {
                alert("Vui lòng chọn một sự kiện!");
                return;
            }

            // Hiển thị vùng quét QR
            document.getElementById('reader').style.display = "block";
            startQRScannerBtn.style.display = "none";
            stopQRScannerBtn.style.display = "inline-block";

            qrScanner.start(
                { facingMode: "environment" }, // Camera sau
                { fps: 10, qrbox: 250 },
                async (decodedText) => {
                    console.log("Mã QR phát hiện:", decodedText);

                    // Gửi mã QR đến API process_qr.php
                    const response = await fetch(`/hethongdiemdanh/modules/process_qr.php?qr_code=${encodeURIComponent(decodedText)}&event_id=${encodeURIComponent(eventId)}`);
                    const data = await response.json();

                    if (data.success) {
                        document.getElementById('userFullName').textContent = data.full_name;
                        document.getElementById('userClass').textContent = data.class;
                        document.getElementById('userAction').textContent = data.action;
                        document.getElementById('attendanceMessage').textContent = data.message;

                        // Hiển thị ảnh học sinh
                        document.getElementById('studentPhoto').src = data.profile_photo;

                        // Hiển thị thông tin chi tiết
                        document.getElementById('checkInDetails').style.display = 'block';
                    } else {
                        alert(data.error || "Không thể xử lý mã QR.");
                    }
                },
                (error) => {
                    console.error('Lỗi khi quét mã QR:', error);
                }
            );
        });

        // Dừng quét mã QR
        stopQRScannerBtn.addEventListener('click', () => {
            qrScanner.stop();
            document.getElementById('reader').style.display = "none";
            startQRScannerBtn.style.display = "inline-block";
            stopQRScannerBtn.style.display = "none";
        });
    </script>
</body>
</html>