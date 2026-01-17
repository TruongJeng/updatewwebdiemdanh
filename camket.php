<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>JSignature Example</title>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/jquery-signature/js/jSignature.min.js"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jquery-signature/css/jquery.signature.css">
  <style>
    .signature-container {
      text-align: center;
    }
    #signature-canvas {
      border: 1px solid #ccc;
      width: 500px;
      height: 200px;
    }
    button {
      margin: 10px;
    }
  </style>
</head>
<body>
  <div class="signature-container">
    <h1>JSignature Example</h1>
    <div id="signature-canvas"></div>
    <button id="clear-button">Xóa chữ ký</button>
    <button id="save-button">Xuất chữ ký</button>
  </div>

  <script>
    $(document).ready(function () {
      const $sigdiv = $("#signature-canvas").jSignature();

      $("#clear-button").click(function () {
        $sigdiv.jSignature("reset");
      });

      $("#save-button").click(function () {
        const data = $sigdiv.jSignature("getData", "image"); // Xuất chữ ký dưới dạng hình ảnh
        const img = `<img src="data:${data[0]},${data[1]}" />`;
        $("body").append(img);
        alert("Chữ ký đã được lưu và hiển thị dưới dạng hình ảnh!");
      });
    });
  </script>
</body>
</html>