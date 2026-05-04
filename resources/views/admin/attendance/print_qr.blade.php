<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print QR Absensi</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 40px 20px;
            background: #fff;
        }
        .print-title {
            font-size: 32px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 12px;
            text-align: center;
            letter-spacing: 0.02em;
        }
        .print-subtitle {
            font-size: 16px;
            color: #475569;
            margin-bottom: 30px;
            text-align: center;
        }
        .qr-box {
            padding: 24px;
            border: 3px solid #0f172a;
            border-radius: 16px;
            margin-bottom: 24px;
        }
        .qr-box canvas { display: block; }
        .store-name {
            font-size: 14px;
            color: #64748b;
            text-align: center;
            margin-top: 16px;
        }
        .toolbar {
            margin-top: 30px;
            display: flex;
            gap: 10px;
        }
        .toolbar button, .toolbar a {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            color: #fff;
        }
        .btn-print { background: #1d4ed8; }
        .btn-back { background: #475569; }

        @media print {
            .toolbar { display: none; }
            body { padding: 0; justify-content: center; }
            .qr-box { border-width: 2px; }
        }
    </style>
</head>

<body>
    <div class="print-title">SCAN UNTUK ABSENSI</div>
    <div class="print-subtitle">Arahkan kamera ke QR code di bawah ini</div>

    <div class="qr-box">
        <div id="printQrContainer" style="width:300px;height:300px;"></div>
    </div>

    <div class="store-name">{{ config('app.name', 'Order App') }}</div>

    <div class="toolbar">
        <button class="btn-print" onclick="window.print()">🖨️ Print</button>
        <a class="btn-back" href="{{ route('admin.attendance.qr') }}">⬅️ Kembali</a>
    </div>

    <script src="{{ asset('js/vendor/qrcode.min.js') }}"></script>
    <script>
        var container = document.getElementById('printQrContainer');
        new QRCode(container, {
            text: @json($qrValue),
            width: 300,
            height: 300,
            colorDark: '#000000',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.H
        });
    </script>
</body>

</html>
