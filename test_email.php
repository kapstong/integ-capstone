<?php
/**
 * Email Configuration Test Script
 * Use this to test if your email is working properly
 */

require_once 'config.php';
require_once 'includes/database.php';
require_once 'includes/mailer.php';

// Check if form was submitted
$testResult = null;
$testEmail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $testEmail = $_POST['email'] ?? '';

    if (!empty($testEmail) && filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
        $mailer = Mailer::getInstance();

        // Test 1: Send plain text email
        $result1 = $mailer->send(
            $testEmail,
            'ATIERA Email Test - Plain Text',
            "This is a plain text test email.\n\nIf you receive this, your basic email configuration is working!",
            []
        );

        // Test 2: Send HTML email
        $result2 = $mailer->send(
            $testEmail,
            'ATIERA Email Test - HTML',
            '<h2>HTML Email Test</h2><p>If you see this formatted, <strong>HTML emails are working!</strong></p>',
            ['html' => true]
        );

        // Test 3: Send verification code (actual format)
        $testCode = '123456';
        $result3 = $mailer->sendVerificationCode($testEmail, $testCode, 'Test User');

        $testResult = [
            'plain' => $result1,
            'html' => $result2,
            'verification' => $result3
        ];
    } else {
        $testResult = ['error' => 'Invalid email address'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ATIERA Email Test</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1b2f73 0%, #0f1c49 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            padding: 40px;
        }
        h1 {
            color: #1b2f73;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            margin-bottom: 30px;
            border-radius: 4px;
        }
        .info-box h3 {
            color: #1976d2;
            margin-bottom: 10px;
            font-size: 16px;
        }
        .info-box p, .info-box ul {
            color: #555;
            font-size: 14px;
            line-height: 1.6;
        }
        .info-box ul {
            margin-left: 20px;
            margin-top: 10px;
        }
        .warning-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin-bottom: 30px;
            border-radius: 4px;
        }
        .warning-box h3 {
            color: #856404;
            margin-bottom: 10px;
            font-size: 16px;
        }
        .warning-box p {
            color: #856404;
            font-size: 14px;
            line-height: 1.6;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }
        input[type="email"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        input[type="email"]:focus {
            outline: none;
            border-color: #1b2f73;
        }
        .btn {
            background: linear-gradient(135deg, #1b2f73 0%, #0f1c49 100%);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .btn:hover {
            transform: translateY(-2px);
        }
        .result {
            margin-top: 30px;
            padding: 20px;
            border-radius: 6px;
        }
        .result.success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
        }
        .result.error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
        }
        .result h3 {
            margin-bottom: 15px;
            font-size: 18px;
        }
        .result.success h3 { color: #155724; }
        .result.error h3 { color: #721c24; }
        .test-item {
            padding: 10px;
            margin: 5px 0;
            border-radius: 4px;
            font-size: 14px;
        }
        .test-item.pass {
            background: #d4edda;
            color: #155724;
        }
        .test-item.fail {
            background: #f8d7da;
            color: #721c24;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
        }
        .config-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 6px;
            margin-top: 30px;
        }
        .config-section h3 {
            color: #333;
            margin-bottom: 15px;
        }
        .config-section pre {
            background: white;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
            font-size: 12px;
            line-height: 1.5;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📧 ATIERA Email Configuration Test</h1>
        <p class="subtitle">Test your email configuration to ensure verification codes are delivered</p>

        <div class="info-box">
            <h3>ℹ️ About This Test</h3>
            <p>This tool will send three test emails to verify your email configuration:</p>
            <ul>
                <li><strong>Plain Text Email</strong> - Basic email functionality</li>
                <li><strong>HTML Email</strong> - Formatted email with styling</li>
                <li><strong>Verification Code</strong> - Actual privacy mode email format</li>
            </ul>
        </div>

        <div class="warning-box">
            <h3>⚠️ WAMP Email Configuration Required</h3>
            <p>WAMP doesn't send emails by default. You need to configure SMTP settings in <code>php.ini</code> or use a mail service.</p>
        </div>

        <form method="POST">
            <div class="form-group">
                <label for="email">📬 Test Email Address</label>
                <input type="email" id="email" name="email"
                       placeholder="Enter your email address"
                       value="<?= htmlspecialchars($testEmail) ?>" required>
            </div>
            <button type="submit" class="btn">🚀 Send Test Emails</button>
        </form>

        <?php if ($testResult !== null): ?>
            <?php if (isset($testResult['error'])): ?>
                <div class="result error">
                    <h3>❌ Error</h3>
                    <p><?= htmlspecialchars($testResult['error']) ?></p>
                </div>
            <?php else: ?>
                <div class="result <?= ($testResult['plain'] && $testResult['html'] && $testResult['verification']) ? 'success' : 'error' ?>">
                    <h3>Test Results</h3>
                    <div class="test-item <?= $testResult['plain'] ? 'pass' : 'fail' ?>">
                        <?= $testResult['plain'] ? '✅' : '❌' ?> Plain Text Email
                    </div>
                    <div class="test-item <?= $testResult['html'] ? 'pass' : 'fail' ?>">
                        <?= $testResult['html'] ? '✅' : '❌' ?> HTML Email
                    </div>
                    <div class="test-item <?= $testResult['verification'] ? 'pass' : 'fail' ?>">
                        <?= $testResult['verification'] ? '✅' : '❌' ?> Verification Code Email
                    </div>

                    <?php if ($testResult['plain'] && $testResult['html'] && $testResult['verification']): ?>
                        <p style="margin-top: 15px; color: #155724;">
                            <strong>🎉 All tests passed!</strong> Check your inbox at <code><?= htmlspecialchars($testEmail) ?></code>
                        </p>
                    <?php else: ?>
                        <p style="margin-top: 15px; color: #721c24;">
                            <strong>⚠️ Email configuration needs setup.</strong> See instructions below.
                        </p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="config-section">
            <h3>🔧 Email Configuration Guide</h3>
            <p style="margin-bottom: 15px;">To enable email sending in WAMP, edit <code>C:\wamp64\bin\php\php8.x.x\php.ini</code>:</p>
            <pre>[mail function]
; For Windows
SMTP = smtp.gmail.com
smtp_port = 587
sendmail_from = your-email@gmail.com
sendmail_path = "C:\wamp64\sendmail\sendmail.exe -t"

; Or use your company SMTP
SMTP = mail.yourcompany.com
smtp_port = 25</pre>

            <p style="margin: 15px 0;"><strong>For Gmail SMTP:</strong></p>
            <ol style="margin-left: 20px; color: #555; font-size: 14px; line-height: 1.8;">
                <li>Use Gmail App Password (not your regular password)</li>
                <li>Go to: Google Account → Security → 2-Step Verification → App Passwords</li>
                <li>Generate password for "Mail" on "Windows Computer"</li>
                <li>Update <code>.env</code> file with SMTP credentials</li>
            </ol>
        </div>

        <div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 6px; text-align: center;">
            <p style="color: #666; font-size: 14px;">
                Once emails are working, <a href="admin/index.php" style="color: #1b2f73; font-weight: 600;">return to dashboard</a>
            </p>
        </div>
    </div>
</body>
</html>
