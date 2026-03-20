<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Verification Code</title>
    <style>
        body { font-family: sans-serif; background: #f9fdf2; margin: 0; padding: 40px 20px; color: #1a1a1a; }
        .container { max-width: 480px; margin: 0 auto; background: #fff; border-radius: 8px; padding: 40px; }
        .code { font-size: 2.5rem; font-weight: 700; letter-spacing: 0.3em; color: #004e89; text-align: center; margin: 24px 0; }
        .note { font-size: 0.875rem; color: #666; margin-top: 24px; }
    </style>
</head>
<body>
    <div class="container">
        <p>Hi there,</p>
        <p>You requested to book a court at <strong>{{ $hubName }}</strong>. Use the code below to verify your email and complete your booking:</p>

        <div class="code">{{ $code }}</div>

        <p>This code expires in <strong>10 minutes</strong>.</p>

        <p class="note">
            Your email is required to verify your identity and prevent duplicate or spam bookings.
            If you did not request this, you can safely ignore this email.
        </p>
    </div>
</body>
</html>
