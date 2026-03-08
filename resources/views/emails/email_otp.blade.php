<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Email OTP</title>
</head>

<body>
    <h2>Email Verification</h2>
    <p>{{ $name }} your OTP code is:</p>

    <h1 style="letter-spacing:3px">{{ $otp }}</h1>

    <p>This OTP is valid for a short time.</p>
    <p>If you didn’t request this, please ignore this email.</p>
</body>

</html>
