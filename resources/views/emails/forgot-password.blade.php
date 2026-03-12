<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Password Reset</title>

<style>

body{
    font-family: Arial;
    background:#f4f6f9;
    padding:40px;
}

.container{
    max-width:600px;
    margin:auto;
    background:white;
    padding:30px;
    border-radius:10px;
    box-shadow:0 5px 20px rgba(0,0,0,0.1);
}

.header{
    text-align:center;
    font-size:24px;
    font-weight:bold;
    color:#333;
}

.otp{
    font-size:32px;
    letter-spacing:5px;
    text-align:center;
    background:#f2f2f2;
    padding:15px;
    margin:25px 0;
    border-radius:6px;
    font-weight:bold;
}

.footer{
    font-size:12px;
    color:#888;
    text-align:center;
    margin-top:30px;
}

</style>

</head>

<body>

<div class="container">

<div class="header">
My Dairee Password Reset
</div>

<p>Hello {{ $name }},</p>

<p>You requested to reset your password.</p>

<p>Your OTP is:</p>

<div class="otp">
{{ $otp }}
</div>

<p>This OTP will expire in <b>10 minutes</b>.</p>

<p>If you didn't request this, please ignore this email.</p>

<div class="footer">
© 2026 My Dairee
</div>

</div>

</body>
</html>