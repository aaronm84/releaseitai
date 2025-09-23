<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Magic Link - ReleaseIt</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .button {
            display: inline-block;
            background-color: #4F46E5;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
            margin: 20px 0;
        }
        .button:hover {
            background-color: #4338CA;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            font-size: 12px;
            color: #666;
        }
        .security-note {
            background-color: #FEF3C7;
            border: 1px solid #F59E0B;
            padding: 15px;
            border-radius: 6px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>ReleaseIt</h1>
        <h2>Your Magic Link</h2>
    </div>

    <p>Hello,</p>

    <p>You requested a magic link to sign in to your ReleaseIt account. Click the button below to securely log in:</p>

    <div style="text-align: center;">
        <a href="{{ $url }}" class="button">Sign In to ReleaseIt</a>
    </div>

    <p>Or copy and paste this URL into your browser:</p>
    <p style="word-break: break-all; background-color: #f5f5f5; padding: 10px; border-radius: 4px;">
        {{ $url }}
    </p>

    <div class="security-note">
        <strong>Security Note:</strong>
        <ul>
            <li>This link will expire in 15 minutes</li>
            <li>This link can only be used once</li>
            <li>If you didn't request this link, you can safely ignore this email</li>
        </ul>
    </div>

    <div class="footer">
        <p>If you have any questions, please contact our support team.</p>
        <p>&copy; {{ date('Y') }} ReleaseIt. All rights reserved.</p>
    </div>
</body>
</html>