<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thanh toán qua NPay</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background: #f6f7fb; margin: 0; padding: 24px; }
        .card { max-width: 480px; margin: 40px auto; background: #fff; border-radius: 16px; box-shadow: 0 8px 32px rgba(0,0,0,.08); padding: 32px; text-align: center; }
        .card h1 { margin: 0 0 8px; color: #1a73e8; font-size: 22px; }
        .card .amount { font-size: 28px; font-weight: 700; color: #111; margin: 12px 0; }
        .card img.qr { width: 320px; max-width: 100%; height: auto; border-radius: 8px; }
        .info { text-align: left; margin-top: 16px; background: #fafbff; padding: 16px; border-radius: 8px; font-size: 14px; }
        .info div { margin: 4px 0; }
        .info b { display: inline-block; min-width: 110px; color: #555; }
        .note { color: #888; font-size: 13px; margin-top: 16px; }
        .brand { color: #1a73e8; font-weight: 600; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Quét QR để thanh toán</h1>
        <div class="amount">{{ number_format((float) ($amount ?? 0), 0, ',', '.') }} đ</div>
        <img class="qr" src="{{ $qrUrl }}" alt="NPay VietQR">
        <div class="info">
            <div><b>Ngân hàng:</b> {{ $bankName ?? config('npay.bank_bin') }}</div>
            <div><b>Số tài khoản:</b> {{ $accountNumber ?? config('npay.account_number') }}</div>
            <div><b>Chủ tài khoản:</b> {{ $accountHolder ?? config('npay.account_holder') }}</div>
            <div><b>Nội dung CK:</b> <code>{{ $description ?? '' }}</code></div>
        </div>
        <div class="note">Sau khi chuyển khoản, đơn hàng sẽ được xử lý tự động.<br>Cung cấp bởi <span class="brand">NPay</span>.</div>
    </div>
</body>
</html>
