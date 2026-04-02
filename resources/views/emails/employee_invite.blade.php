<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Employee Onboarding</title>
</head>
<body style="margin:0; padding:0; font-family: Arial, sans-serif; background-color:#f4f4f4;">

    <table width="100%" cellpadding="0" cellspacing="0" style="padding:20px;">
        <tr>
            <td align="center">

                <table width="600" style="background:#ffffff; border-radius:10px; overflow:hidden; box-shadow:0 0 10px rgba(0,0,0,0.1);">

                    <!-- Header -->
                    <tr>
                        <td style="background:#4CAF50; padding:20px; text-align:center; color:#ffffff;">
                            <h2>Welcome to Chrispp 🚀</h2>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding:30px; color:#333;">
                            <h3>Hello {{ $data['name'] }} {{ $data['last_name'] }},</h3>

                            <p>
                                We are excited to have you onboard! 🎉  
                                Please complete your profile by submitting your details and documents.
                            </p>

                            <p style="text-align:center; margin:30px 0;">
                                <a href="{{ $data['link'] }}"
                                   style="background:#4CAF50; color:#fff; padding:12px 25px; text-decoration:none; border-radius:5px;">
                                    Complete Your Profile
                                </a>
                            </p>

                            <p>
                                This link will help you upload certificates, personal details, and other required documents.
                            </p>

                            <p>If you have any questions, feel free to contact HR.</p>

                            <br>
                            <p>Regards,<br><strong>Chrispp HR Team</strong></p>
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