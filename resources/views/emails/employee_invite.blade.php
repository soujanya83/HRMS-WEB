<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Welcome to {{ $data['organization_name'] }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f6f9fc; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" style="padding:40px 20px;">
<tr>
<td align="center">

<table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 10px 30px rgba(0,0,0,0.05);">

    <!-- Header -->
    <tr>
        <td style="padding:30px; text-align:center; border-bottom:1px solid #eee;">
            <h2 style="margin:0; color:#111;">Welcome to {{ $data['organization_name'] }} 👋</h2>
        </td>
    </tr>

    <!-- Body -->
    <tr>
        <td style="padding:35px; color:#444; font-size:15px; line-height:1.6;">

            <p>Hi <strong>{{ $data['name'] }} {{ $data['last_name'] }}</strong>,</p>

            <p>
                Your account has been successfully created. You can now access the platform using the credentials below.
            </p>

            <!-- Credentials Card -->
            <table width="100%" cellpadding="12" cellspacing="0" style="background:#f8fafc; border-radius:8px; margin:25px 0;">
                <tr>
                    <td style="color:#888;">Email</td>
                    <td style="font-weight:600;">{{ $data['email'] }}</td>
                </tr>
                <tr>
                    <td style="color:#888;">Temporary Password</td>
                    <td style="font-weight:600;">{{ $data['password'] }}</td>
                </tr>
            </table>

            <p>
                For security reasons, please change your password immediately after your first login.
            </p>

            <!-- CTA -->
            <p style="text-align:center; margin:35px 0;">
                <a href="{{ $data['smart_link'] }}"
                   style="background:#4F46E5; color:#ffffff; padding:14px 28px; text-decoration:none; border-radius:8px; font-weight:600; display:inline-block;">
                    Open App & Login
                </a>
            </p>

            <p style="font-size:13px; color:#888; text-align:center;">
                This link will automatically redirect you to the correct app store based on your device.
            </p>

            <p>If you have any questions, feel free to contact HR.</p>

            <br>

            <!-- Disclaimer (UNCHANGED) -->
            <p style="font-size:12px; color:#666;">
<strong>Disclaimer :</strong><br>
This communication, including any links and attachments, is confidential and intended solely for the named recipient. It may contain sensitive personal and/or employment-related information. Any unauthorised access, use, disclosure, copying, or distribution is strictly prohibited and may be unlawful.
If you are not the intended recipient, you must not access or rely on this information. Please notify the sender immediately and permanently delete this communication from your system.
CHRISPP does not accept liability for any unauthorised use of this communication or for any loss or damage arising from access to the link outside its intended purpose. Users are responsible for maintaining the confidentiality of their login credentials and for accessing the system in a secure manner.
            </p>

        </td>
    </tr>

    <!-- Footer -->
    <tr>
        <td style="text-align:center; padding:20px; font-size:12px; color:#aaa;">
            © {{ date('Y') }} Chrispp. All rights reserved.
        </td>
    </tr>

</table>

</td>
</tr>
</table>

</body>
</html>