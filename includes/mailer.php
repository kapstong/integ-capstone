<?php
/**
 * Simple Email Mailer Class
 * Handles sending emails with proper formatting and error handling
 */

class Mailer {
    private static $instance = null;
    private $fromEmail;
    private $fromName;
    private $smtpEnabled = false;
    private $smtpHost;
    private $smtpPort;
    private $smtpUser;
    private $smtpPass;
    private $smtpEncryption;

    private function __construct() {
        $this->fromEmail = Config::get('mail.from_address') ?: 'noreply@atiera.com';
        $this->fromName = Config::get('mail.from_name') ?: 'ATIERA Finance';

        $this->smtpHost = Config::get('mail.host');
        $this->smtpPort = (int) Config::get('mail.port', 587);
        $this->smtpUser = Config::get('mail.username');
        $this->smtpPass = Config::get('mail.password');
        $this->smtpEncryption = Config::get('mail.encryption', 'tls');

        $mailer = Config::get('mail.mailer', 'smtp');
        $this->smtpEnabled = ($mailer === 'smtp')
            && !empty($this->smtpHost)
            && !empty($this->smtpUser)
            && !empty($this->smtpPass);
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Send email
     *
     * @param string $to Recipient email address
     * @param string $subject Email subject
     * @param string $message Email body (plain text)
     * @param array $options Optional settings (html, headers, etc.)
     * @return bool Success status
     */
    public function send($to, $subject, $message, $options = []) {
        try {
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                error_log("Mailer Error: Invalid email address: $to");
                return false;
            }

            $headers = $this->buildHeaders($options);

            $isHtml = isset($options['html']) && $options['html'] === true;
            if ($isHtml) {
                $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
                $message = $this->wrapHtmlMessage($message, $subject);
            } else {
                $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            }

            error_log("=== SENDING EMAIL ===");
            error_log("To: $to");
            error_log("From: {$this->fromEmail}");
            error_log("Subject: $subject");
            error_log("Environment: " . (Config::isDevelopment() ? 'Development' : 'Production'));
            error_log("====================");

            $sent = false;
            if ($this->smtpEnabled) {
                $sent = $this->sendSmtp($to, $subject, $message, $headers);
            }
            if (!$sent) {
                $sent = @mail($to, $subject, $message, $headers);
            }

            if (!$sent) {
                $lastError = error_get_last();
                error_log("Mailer Error: Failed to send email to $to");
                if ($lastError) {
                    error_log("PHP Error: " . $lastError['message']);
                }
                return false;
            }

            error_log("Email sent successfully to $to");
            return true;

        } catch (Exception $e) {
            error_log("Mailer Exception: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return false;
        }
    }

    private function buildHeaders($options = []) {
        $headers = [];

        $headers[] = "From: {$this->fromName} <{$this->fromEmail}>";
        $replyTo = $options['reply_to'] ?? $this->fromEmail;
        $headers[] = "Reply-To: $replyTo";
        $headers[] = "X-Mailer: PHP/" . phpversion();
        $headers[] = "MIME-Version: 1.0";

        if (isset($options['priority'])) {
            $headers[] = "X-Priority: {$options['priority']}";
        }

        return implode("\r\n", $headers) . "\r\n";
    }

    private function wrapHtmlMessage($message, $subject) {
        return '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($subject) . '</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f5f5f5;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f5f5f5; padding: 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" border="0" style="background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <tr>
                        <td style="background: linear-gradient(135deg, #1b2f73 0%, #0f1c49 100%); padding: 30px; text-align: center; border-radius: 8px 8px 0 0;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 24px; font-weight: bold;">
                                ATIERA Finance
                            </h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 40px 30px;">
                            ' . $message . '
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color: #f8f9fa; padding: 20px 30px; text-align: center; border-radius: 0 0 8px 8px; border-top: 1px solid #e9ecef;">
                            <p style="margin: 0; color: #6c757d; font-size: 12px;">
                                This is an automated message from ATIERA Financial Management System<br>
                                Please do not reply to this email.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
    }

    public function sendVerificationCode($to, $code, $firstName = '') {
        $subject = 'ATIERA - Privacy Mode Verification Code';

        $message = '
        <div style="color: #333; line-height: 1.6;">
            <p style="font-size: 16px; margin-bottom: 10px;">Hello' . ($firstName ? ' ' . htmlspecialchars($firstName) : '') . ',</p>

            <p style="font-size: 14px; color: #666; margin-bottom: 20px;">
                You requested to view financial amounts in the ATIERA system. Please use the verification code below:
            </p>

            <div style="background: linear-gradient(135deg, #1b2f73 0%, #0f1c49 100%); padding: 20px; text-align: center; border-radius: 8px; margin: 30px 0;">
                <p style="color: #ffffff; font-size: 14px; margin: 0 0 10px 0; text-transform: uppercase; letter-spacing: 2px;">
                    Verification Code
                </p>
                <p style="color: #ffffff; font-size: 36px; font-weight: bold; margin: 0; letter-spacing: 8px; font-family: monospace;">
                    ' . htmlspecialchars($code) . '
                </p>
            </div>

            <div style="background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0;">
                <p style="margin: 0; color: #856404; font-size: 13px;">
                    <strong>Important:</strong> This code will expire in <strong>5 minutes</strong>.
                </p>
            </div>

            <p style="font-size: 14px; color: #666; margin-top: 20px;">
                If you did not request this code, please ignore this email or contact your system administrator.
            </p>

            <p style="font-size: 14px; color: #333; margin-top: 30px;">
                Best regards,<br>
                <strong>ATIERA Financial Management System</strong>
            </p>
        </div>';

        return $this->send($to, $subject, $message, ['html' => true, 'priority' => 1]);
    }

    public function testConnection() {
        $testEmail = Config::get('company.email') ?: 'admin@atiera.com';
        $subject = 'ATIERA Mailer Test';
        $message = 'This is a test email from ATIERA Financial Management System. If you receive this, email is working properly!';

        return $this->send($testEmail, $subject, $message);
    }

    private function sendSmtp($to, $subject, $message, $headers) {
        $host = $this->smtpHost;
        $port = $this->smtpPort;
        $encryption = strtolower($this->smtpEncryption);

        $remote = ($encryption === 'ssl') ? "ssl://{$host}" : $host;
        $fp = @fsockopen($remote, $port, $errno, $errstr, 10);
        if (!$fp) {
            error_log("SMTP Error: $errstr ($errno)");
            return false;
        }

        if (!$this->expectSmtpCode($fp, 220)) {
            fclose($fp);
            return false;
        }

        $this->smtpCommand($fp, "EHLO " . ($_SERVER['HTTP_HOST'] ?? 'localhost'), 250);

        if ($encryption === 'tls') {
            $this->smtpCommand($fp, "STARTTLS", 220);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                error_log("SMTP Error: Failed to start TLS");
                fclose($fp);
                return false;
            }
            $this->smtpCommand($fp, "EHLO " . ($_SERVER['HTTP_HOST'] ?? 'localhost'), 250);
        }

        if (!empty($this->smtpUser)) {
            $this->smtpCommand($fp, "AUTH LOGIN", 334);
            $this->smtpCommand($fp, base64_encode($this->smtpUser), 334);
            $this->smtpCommand($fp, base64_encode($this->smtpPass), 235);
        }

        $this->smtpCommand($fp, "MAIL FROM:<{$this->fromEmail}>", 250);
        $this->smtpCommand($fp, "RCPT TO:<{$to}>", [250, 251]);
        $this->smtpCommand($fp, "DATA", 354);

        $smtpHeaders = "To: {$to}\r\nSubject: {$subject}\r\n" . $headers;
        $data = $smtpHeaders . "\r\n" . $message . "\r\n.";
        $this->smtpCommand($fp, $data, 250);

        $this->smtpCommand($fp, "QUIT", 221);
        fclose($fp);
        return true;
    }

    private function smtpCommand($fp, $command, $expect) {
        fwrite($fp, $command . "\r\n");
        return $this->expectSmtpCode($fp, $expect);
    }

    private function expectSmtpCode($fp, $expect) {
        $response = '';
        while ($line = fgets($fp, 515)) {
            $response .= $line;
            if (preg_match('/^\\d{3} /', $line)) {
                break;
            }
        }

        $code = intval(substr($response, 0, 3));
        if (is_array($expect)) {
            if (!in_array($code, $expect, true)) {
                error_log("SMTP Error: Unexpected response {$response}");
                return false;
            }
        } else {
            if ($code !== $expect) {
                error_log("SMTP Error: Unexpected response {$response}");
                return false;
            }
        }

        return true;
    }
}
?>
