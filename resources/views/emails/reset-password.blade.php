<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password Akun — TicketingFlow</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f8fafc;
            color: #334155;
            margin: 0;
            padding: 0;
            line-height: 1.6;
        }
        .wrapper {
            width: 100%;
            max-width: 580px;
            margin: 30px auto;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            border: 1px solid #e2e8f0;
        }
        .header {
            background-color: #0284c7;
            padding: 24px 32px;
            text-align: center;
        }
        .header h1 {
            color: #ffffff;
            font-size: 22px;
            font-weight: 800;
            margin: 0;
            letter-spacing: -0.5px;
        }
        .header span {
            color: #bae6fd;
        }
        .content {
            padding: 32px;
        }
        .greeting {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 12px;
        }
        .text {
            font-size: 14px;
            color: #475569;
            margin-bottom: 24px;
        }
        .button-wrapper {
            text-align: center;
            margin: 32px 0;
        }
        .btn {
            display: inline-block;
            background-color: #0284c7;
            color: #ffffff !important;
            font-weight: 700;
            font-size: 14px;
            padding: 12px 28px;
            border-radius: 8px;
            text-decoration: none;
            box-shadow: 0 2px 4px rgba(2, 132, 199, 0.25);
        }
        .btn:hover {
            background-color: #0369a1;
        }
        .warning-box {
            background-color: #fffbeb;
            border: 1px solid #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 14px 16px;
            border-radius: 6px;
            margin-bottom: 24px;
            font-size: 12px;
            color: #92400e;
        }
        .footer {
            background-color: #f1f5f9;
            padding: 20px 32px;
            text-align: center;
            font-size: 12px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
        }
        .link-alt {
            word-break: break-all;
            font-size: 11px;
            color: #64748b;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px dashed #cbd5e1;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <h1>Ticketing<span>Flow</span></h1>
        </div>
        <div class="content">
            <div class="greeting">Halo, {{ $user->name }}!</div>
            <p class="text">
                Kami menerima permintaan untuk mereset password akun Client Anda di portal <strong>TicketingFlow</strong>. 
                Klik tombol di bawah ini untuk membuat password baru:
            </p>
            <div class="button-wrapper">
                <a href="{{ $resetUrl }}" class="btn" target="_blank">Reset Password Akun</a>
            </div>
            <div class="warning-box">
                ⏱️ <strong>Catatan Keamanan:</strong> Link reset password ini hanya berlaku selama <strong>60 menit</strong>. 
                Jika Anda tidak merasa meminta reset password, abaikan email ini dan akun Anda akan tetap aman.
            </div>
            <div class="link-alt">
                Jika tombol di atas tidak bisa diklik, salin dan tempel tautan berikut di browser Anda:<br>
                <a href="{{ $resetUrl }}" style="color: #0284c7;">{{ $resetUrl }}</a>
            </div>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} TicketingFlow. Hak Cipta Dilindungi.<br>
            Pesan ini dikirimkan secara otomatis oleh sistem penanganan kendala client.
        </div>
    </div>
</body>
</html>
