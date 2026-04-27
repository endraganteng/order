<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Label QR Code Rak</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 18px;
            background: #f8fafc;
            color: #0f172a;
        }

        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 16px;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px;
        }

        .toolbar-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn {
            border: none;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 13px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            color: #fff;
            background: #1d4ed8;
        }

        .btn-secondary {
            background: #475569;
        }

        .labels-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 12px;
        }

        .label-item {
            background: #fff;
            border: 1px dashed #94a3b8;
            border-radius: 10px;
            padding: 10px;
            break-inside: avoid;
            text-align: center;
        }

        .label-title {
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 4px;
            text-align: center;
        }

        .label-sub {
            font-size: 12px;
            color: #475569;
            margin-bottom: 6px;
            text-align: center;
        }

        .label-code {
            font-size: 11px;
            color: #64748b;
            margin-top: 4px;
            font-weight: 700;
            text-align: center;
        }
        .label-qrcode {
            width: 110px;
            height: 110px;
            margin-top: 4px;
            margin-left: auto;
            margin-right: auto;
            background: #fff;
        }
        .label-qrcode > img,
        .label-qrcode > canvas {
            display: block;
            margin: 0 auto;
        }

        @media print {
            body {
                background: #fff;
                padding: 8px;
            }

            .toolbar {
                display: none;
            }

            .labels-grid {
                gap: 8px;
            }

            .label-item {
                border: 1px solid #94a3b8;
                page-break-inside: avoid;
            }
        }
    </style>
</head>

<body>
    <div class="toolbar">
        <div>
            <div style="font-size:18px;font-weight:700;">🖨️ Print Label QR Code Rak</div>
            <div style="font-size:12px;color:#475569;">Scope: {{ $labelScope }} • Total Label: {{ count($racks) }} • Waktu: {{ date('d/m/Y H:i', (int) $printedAt) }}</div>
        </div>
        <div class="toolbar-actions">
            <button type="button" class="btn" onclick="window.print()">🖨️ Print Sekarang</button>
            <a href="{{ route('admin.racks.index') }}" class="btn btn-secondary">⬅️ Kembali ke Master Rak</a>
        </div>
    </div>

    <div class="labels-grid">
        @foreach($racks as $rack)
            @php
                $rackName = (string) ($rack['name'] ?? '-');
                $rackLocation = (string) ($rack['location'] ?? '-');
                $barcodeValue = (string) ($rack['barcode_value'] ?? '');
            @endphp
            <div class="label-item">
                <div class="label-title">{{ $rackName }}</div>
                <div class="label-sub">📍 {{ $rackLocation }}</div>
                @if($barcodeValue !== '')
                    <div class="label-qrcode rack-qrcode" data-code="{{ $barcodeValue }}"></div>
                @else
                    <div style="font-size:12px;color:#b91c1c;">QR code belum tersedia</div>
                @endif
                <div class="label-code">Kode: {{ $barcodeValue !== '' ? $barcodeValue : '-' }}</div>
            </div>
        @endforeach
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        document.querySelectorAll('.rack-qrcode').forEach((el) => {
            const value = String(el.getAttribute('data-code') || '').trim();
            if (!value) {
                return;
            }

            try {
                el.innerHTML = '';
                new QRCode(el, {
                    text: value,
                    width: 110,
                    height: 110,
                    correctLevel: QRCode.CorrectLevel.M,
                });
            } catch (error) {
                el.outerHTML = '<span style="font-size:12px;color:#b91c1c;">QR code invalid</span>';
            }
        });
    </script>
</body>

</html>
