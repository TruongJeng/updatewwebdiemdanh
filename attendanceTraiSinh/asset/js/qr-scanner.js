let html5QrCode;
let isScanning = false;

function startScanner(onScanSuccess) {
  const qrRegionId = "qr-video";

  html5QrCode = new Html5Qrcode(qrRegionId);

  Html5Qrcode.getCameras().then(devices => {
    if (!devices || devices.length === 0) {
      alert("Không tìm thấy camera");
      return;
    }

    const cameraId = devices[0].id;

    html5QrCode.start(
      cameraId,
      {
        fps: 10,
        qrbox: { width: 250, height: 250 }
      },
      qrCodeMessage => {
        if (isScanning) return;

        isScanning = true;
        onScanSuccess(qrCodeMessage);

        // chống quét liên tục
        setTimeout(() => {
          isScanning = false;
        }, 2000);
      },
      errorMessage => {
        // bỏ qua lỗi decode
      }
    );
  }).catch(err => {
    alert("Lỗi camera: " + err);
  });
}

function stopScanner() {
  if (html5QrCode) {
    html5QrCode.stop().then(() => {
      html5QrCode.clear();
    });
  }
}
