<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CHRISPP Employee Onboarding Welcome Email</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background: #f4f6fb;
            font-family: Arial, Helvetica, sans-serif;
            color: #1f2937;
        }

        .email-wrapper {
            width: 100%;
            background: #f4f6fb;
            padding: 28px 0;
        }

        .email-container {
            width: 100%;
            max-width: 640px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 8px 28px rgba(17, 24, 39, 0.08);
        }

        .header {
            background: linear-gradient(135deg, #4f46e5, #6d5dfc);
            padding: 32px 36px;
            text-align: center;
            color: #ffffff;
        }

        .brand {
            font-size: 24px;
            font-weight: 700;
            letter-spacing: 0.4px;
            margin-bottom: 8px;
        }

        .header-title {
            font-size: 22px;
            font-weight: 700;
            margin: 8px 0 0;
        }

        .header-subtitle {
            font-size: 14px;
            line-height: 1.6;
            margin: 10px 0 0;
            opacity: 0.95;
        }

        .content {
            padding: 34px 36px 22px;
        }

        .greeting {
            font-size: 16px;
            margin: 0 0 18px;
        }

        .paragraph {
            font-size: 15px;
            line-height: 1.65;
            margin: 0 0 16px;
            color: #374151;
        }

        .login-card {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            margin: 24px 0;
            overflow: hidden;
        }

        .login-card-title {
            background: #eef2ff;
            color: #3730a3;
            font-weight: 700;
            font-size: 14px;
            padding: 14px 18px;
            border-bottom: 1px solid #e5e7eb;
        }

        .login-row {
            display: table;
            width: 100%;
            border-bottom: 1px solid #e5e7eb;
        }

        .login-row:last-child {
            border-bottom: none;
        }

        .login-label,
        .login-value {
            display: table-cell;
            padding: 14px 18px;
            font-size: 14px;
            vertical-align: middle;
        }

        .login-label {
            width: 38%;
            color: #6b7280;
            font-weight: 700;
        }

        .login-value {
            color: #111827;
            font-weight: 600;
            word-break: break-word;
        }

        .login-value a {
            color: #4f46e5;
            text-decoration: none;
        }

        .app-downloads {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 18px;
            margin: 22px 0;
            text-align: center;
        }

        .app-download-title {
            font-size: 15px;
            font-weight: 700;
            color: #111827;
            margin: 0 0 8px;
        }

        .app-download-text {
            font-size: 13px;
            line-height: 1.55;
            color: #4b5563;
            margin: 0 0 16px;
        }

        .button {
            display: inline-block;
            background: #4f46e5;
            color: #ffffff !important;
            text-decoration: none;
            font-size: 15px;
            font-weight: 700;
            padding: 14px 28px;
            border-radius: 8px;
        }

        .note {
            background: #fff7ed;
            border: 1px solid #fed7aa;
            border-radius: 10px;
            padding: 14px 16px;
            font-size: 13px;
            line-height: 1.55;
            color: #7c2d12;
            margin: 20px 0;
        }

        .signature {
            margin-top: 26px;
            font-size: 15px;
            line-height: 1.6;
            color: #374151;
        }

        .footer {
            padding: 22px 36px 30px;
            background: #f9fafb;
            border-top: 1px solid #e5e7eb;
        }

        .support {
            font-size: 13px;
            line-height: 1.6;
            color: #4b5563;
            margin: 0 0 18px;
        }

        .disclaimer-title {
            font-size: 12px;
            font-weight: 700;
            color: #6b7280;
            margin: 0 0 6px;
        }

        .disclaimer {
            font-size: 11px;
            line-height: 1.55;
            color: #6b7280;
            margin: 0;
        }

        .copyright {
            font-size: 11px;
            text-align: center;
            color: #9ca3af;
            margin-top: 22px;
        }

        @media only screen and (max-width: 600px) {
            .email-wrapper {
                padding: 0;
            }

            .email-container {
                border-radius: 0;
            }

            .header,
            .content,
            .footer {
                padding-left: 22px;
                padding-right: 22px;
            }

            .login-label,
            .login-value {
                display: block;
                width: auto;
                padding: 10px 16px;
            }

            .login-label {
                padding-bottom: 3px;
            }

            .login-value {
                padding-top: 3px;
            }
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="email-container">
            <div class="header">
                <div class="brand">CHRISPP</div>
                <div class="header-title">Welcome to the Team</div>
                <div class="header-subtitle">
                    Your HR &amp; Payroll onboarding profile has been created.
                </div>
            </div>

            <div class="content">
                <p class="greeting">Dear {{ $data['name'] }} {{ $data['last_name'] }},</p>

                <p class="paragraph">
                    Welcome to the CHRISPP family. We are delighted to have you join {{ $data['organization_name'] }} and look forward to supporting you through your onboarding journey.
                </p>

                <p class="paragraph">
                    Your employee onboarding account has now been created in the CHRISPP HR &amp; Payroll portal.
                    Please use the details below to log in and complete your employee profile.
                </p>

                <div class="login-card">
                    <div class="login-card-title">Login Details</div>

                    <div class="login-row">
                        <div class="login-label">Portal</div>
                        <div class="login-value">
                            <a href="{{ $data['link'] ?? 'https://chrispp.au/login' }}" target="_blank">https://chrispp.au/login</a>
                        </div>
                    </div>

                    <div class="login-row">
                        <div class="login-label">Username / Email</div>
                        <div class="login-value">{{ $data['email'] }}</div>
                    </div>

                    <div class="login-row">
                        <div class="login-label">Temporary Password</div>
                        <div class="login-value">{{ $data['password'] }}</div>
                    </div>
                </div>

                <div class="app-downloads">
                    <p class="app-download-title">Download the CHRISPP App</p>
                    <p class="app-download-text">
                        Please download the CHRISPP app on your mobile device and use the login details above to access your onboarding profile.
                    </p>
                    <a class="button" href="{{ $data['smart_link'] }}" target="_blank">Download the App</a>
                </div>

                <div class="note">
                    For security reasons, please change your temporary password immediately after your first login.
                    Do not share your password with anyone.
                </div>

                <p class="paragraph">
                    As part of the onboarding process, please complete your employee profile and upload any requested information in the CHRISPP portal.
                    This will help avoid any delay in finalising your onboarding and payroll setup.
                </p>

                <p class="paragraph">
                    If you need help accessing the portal or completing your onboarding profile,
                    please contact the centre team or email <strong>info@chrispp.au</strong>.
                </p>

                <p class="signature">
                    Kind regards,<br>
                    <strong>CHRISPP HR &amp; Payroll Team</strong><br>
                    info@chrispp.au
                </p>
            </div>

            <div class="footer">
                <p class="support">
                    This email was sent from CHRISPP for employee onboarding and payroll setup.
                </p>

                <p class="disclaimer-title">Disclaimer</p>
                <p class="disclaimer">
                    This email, including any links and attachments, is confidential and intended only for the named recipient.
                    It may contain personal or employment-related information. If you have received this email in error,
                    please delete it and notify the sender. CHRISPP will never ask you to share your password by email.
                </p>

                <div class="copyright">&copy; {{ date('Y') }} CHRISPP. All rights reserved.</div>
            </div>
        </div>
    </div>
</body>
</html>
