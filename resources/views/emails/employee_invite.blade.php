<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Employee Login Details</title>
</head>
<body style="margin:0; padding:0; font-family: Arial, sans-serif; background-color:#f4f4f4;">

    <table width="100%" cellpadding="0" cellspacing="0" style="padding:20px;">
        <tr>
            <td align="center">

                <table width="600" style="background:#ffffff; border-radius:10px; overflow:hidden; box-shadow:0 0 10px rgba(0,0,0,0.1);">

                    <!-- Header -->
                    <tr>
                        <td style="background:#4CAF50; padding:20px; text-align:center; color:#ffffff;">
                            <h2>Welcome to {{ $data['organization_name'] }} 🚀</h2>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding:30px; color:#333;">
                            <h3>Hello {{ $data['name'] }} {{ $data['last_name'] }},</h3>

                            <p>
                                We are pleased to welcome you onboard! 🎉  
                                Your employee account has been created successfully.
                            </p>

                            <p>
                                Please find your login credentials below:
                            </p>

                            <table width="100%" cellpadding="10" cellspacing="0" style="background:#f9f9f9; border-radius:5px; margin:20px 0;">
                                <tr>
                                    <td><strong>Email:</strong></td>
                                    <td>{{ $data['email'] ?? 'Your registered email' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Temporary Password:</strong></td>
                                    <td>{{ $data['password'] }}</td>
                                </tr>
                            </table>

                            <p style="text-align:center; margin:30px 0;">
                                <a href="{{ $data['link'] }}"
                                   style="background:#4CAF50; color:#fff; padding:12px 25px; text-decoration:none; border-radius:5px;">
                                    Login to Your Account
                                </a>
                            </p>

                            <p>
                                For security reasons, we strongly recommend that you log in using the above credentials and change your password immediately after your first login.
                            </p>

                            <p>
                                After logging in, please complete your profile by adding your personal details, certificates, and other required documents.
                            </p>

                            <p>If you have any questions, feel free to contact HR.</p>

                            <br>
                         <p>
                           <strong>Disclaimer :</strong><br>
This communication, including any links and attachments, is confidential and intended solely for the named recipient. It may contain sensitive personal and/or employment-related information. Any unauthorised access, use, disclosure, copying, or distribution is strictly prohibited and may be unlawful.
If you are not the intended recipient, you must not access or rely on this information. Please notify the sender immediately and permanently delete this communication from your system.
CHRISPP does not accept liability for any unauthorised use of this communication or for any loss or damage arising from access to the link outside its intended purpose. Users are responsible for maintaining the confidentiality of their login credentials and for accessing the system in a secure manner.
                         </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background:#f1f1f1; text-align:center; padding:15px; font-size:12px;">
                            © {{ date('Y') }} Chrispp. All rights reserved.
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>

</body>
</html>