<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Your Email Address</title>
    <style>
        body { font-family: sans-serif; background: #f9fdf2; margin: 0; padding: 40px 20px; color: #1a1a1a; }
        .container { max-width: 480px; margin: 0 auto; background: #fff; border-radius: 8px; padding: 40px; }
        .header { text-align: center; margin-bottom: 28px; }
        .check { display: inline-block; width: 48px; height: 48px; background: #dcfce7; border-radius: 50%; line-height: 48px; font-size: 24px; margin-bottom: 12px; }
        h1 { margin: 0 0 4px; font-size: 1.25rem; color: #0f1728; }
        .subtitle { color: #64748b; font-size: 0.875rem; margin: 0; }
        .body-text { font-size: 0.875rem; color: #64748b; margin: 24px 0; line-height: 1.6; }
        .btn-wrap { text-align: center; margin: 28px 0; }
        .btn { display: inline-block; background: #004e89; color: #fff; text-decoration: none; font-size: 0.9375rem; font-weight: 600; padding: 12px 32px; border-radius: 8px; }
        .instructions { background: #f0f7ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 14px 16px; font-size: 0.8125rem; color: #1e40af; margin: 20px 0; }
        .expiry { font-size: 0.8125rem; color: #64748b; margin: 16px 0; text-align: center; }
        .footer { font-size: 0.75rem; color: #94a3b8; margin-top: 32px; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="check">✉</div>
            <h1>Verify Your Email</h1>
            <p class="subtitle">Hi <strong>{{ $name }}</strong>, welcome to Aktiv!</p>
        </div>

        <p class="body-text">
            Thanks for signing up! Please verify your email address to activate your account and start booking courts.
        </p>

        <div class="btn-wrap">
            <a href="{{ $verificationUrl }}" class="btn">Verify Email Address</a>
        </div>

        <div class="instructions">
            Click the button above to confirm your email. If the button doesn't work, copy and paste the link into your browser.
        </div>

        <p class="expiry">This link expires in 60 minutes.</p>

        <p class="footer">
            If you did not create an account, no further action is required.<br>
            This is an automated message from Aktiv.
        </p>
    </div>
</body>
</html>
