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

    private function __construct() {
        $this->fromEmail = Config::get('mail.from_address') ?: 'noreply@atiera.com';
        $this->fromName = Config::get('mail.from_name') ?: 'ATIERA Finance';

        // Check if SMTP is configured
        $mailHost = Config::get('mail.host');
        $mailUsername = Config::get('mail.username');
        $mailPassword = Config::get('mail.password');

        // Enable SMTP if credentials are provided or host is configured
        if (!empty($mailHost) && (!empty($mailUsername) || $mailHost !== 'smtp.gmail.com')) {
            $this->smtpEnabled = true;
        }
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
            // Validate email
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                error_log("Mailer Error: Invalid email address: $to");
                return false;
            }

            // Check if HTML email
            $isHtml = isset($options['html']) && $options['html'] === true;
            if ($isHtml) {
                $message = $this->wrapHtmlMessage($message, $subject);
            }

            // Log email attempt
            error_log("=== SENDING EMAIL ===");
            error_log("To: $to");
            error_log("From: {$this->fromEmail}");
            error_log("Subject: $subject");
            error_log("SMTP Enabled: " . ($this->smtpEnabled ? 'Yes' : 'No'));
            error_log("Environment: " . (Config::isDevelopment() ? 'Development' : 'Production'));
            error_log("====================");

            if ($this->smtpEnabled) {
                return $this->sendViaSMTP($to, $subject, $message, $options);
            } else {
                return $this->sendViaMail($to, $subject, $message, $options);
            }

        } catch (Exception $e) {
            error_log("Mailer Exception: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return false;
        }
    }

    /**
     * Send email via SMTP
     */
    private function sendViaSMTP($to, $subject, $message, $options = []) {
        $mailHost = Config::get('mail.host');
        $mailPort = Config::get('mail.port') ?: 587;
        $mailUsername = Config::get('mail.username');
        $mailPassword = Config::get('mail.password');
        $mailEncryption = Config::get('mail.encryption') ?: 'tls';

        // Create socket connection
        $socket = @fsockopen(
            ($mailEncryption === 'ssl' ? 'ssl://' : '') . $mailHost,
            $mailPort,
            $errno,
            $errstr,
            30
        );

        if (!$socket) {
            error_log("SMTP Connection failed: $errstr ($errno)");
            return false;
        }

        // Read server greeting
        $response = fgets($socket, 515);
        if (!$this->checkSMTPResponse($response, '220')) {
            fclose($socket);
            return false;
        }

        // Send EHLO
        fputs($socket, "EHLO " . $_SERVER['SERVER_NAME'] . "\r\n");
        $response = fgets($socket, 515);
        if (!$this->checkSMTPResponse($response, '250')) {
            fclose($socket);
            return false;
        }

        // Start TLS if required
        if ($mailEncryption === 'tls') {
            fputs($socket, "STARTTLS\r\n");
            $response = fgets($socket, 515);
            if (!$this->checkSMTPResponse($response, '220')) {
                fclose($socket);
                return false;
            }

            // Upgrade to TLS
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        }

        // Authenticate
        if (!empty($mailUsername) && !empty($mailPassword)) {
            fputs($socket, "AUTH LOGIN\r\n");
            $response = fgets($socket, 515);
            if (!$this->checkSMTPResponse($response, '334')) {
                fclose($socket);
                return false;
            }

            fputs($socket, base64_encode($mailUsername) . "\r\n");
            $response = fgets($socket, 515);
            if (!$this->checkSMTPResponse($response, '334')) {
                fclose($socket);
                return false;
            }

            fputs($socket, base64_encode($mailPassword) . "\r\n");
            $response = fgets($socket, 515);
            if (!$this->checkSMTPResponse($response, '235')) {
                fclose($socket);
                return false;
            }
        }

        // Send MAIL FROM
        fputs($socket, "MAIL FROM:<{$this->fromEmail}>\r\n");
        $response = fgets($socket, 515);
        if (!$this->checkSMTPResponse($response, '250')) {
            fclose($socket);
            return false;
        }

        // Send RCPT TO
        fputs($socket, "RCPT TO:<$to>\r\n");
        $response = fgets($socket, 515);
        if (!$this->checkSMTPResponse($response, '250')) {
            fclose($socket);
            return false;
        }

        // Send DATA
        fputs($socket, "DATA\r\n");
        $response = fgets($socket, 515);
        if (!$this->checkSMTPResponse($response, '354')) {
            fclose($socket);
            return false;
        }

        // Build email content
        $isHtml = isset($options['html']) && $options['html'] === true;
        $contentType = $isHtml ? 'text/html' : 'text/plain';

        $emailBody = "From: {$this->fromName} <{$this->fromEmail}>\r\n";
        $emailBody .= "To: $to\r\n";
        $emailBody .= "Subject: $subject\r\n";
        $emailBody .= "MIME-Version: 1.0\r\n";
        $emailBody .= "Content-Type: $contentType; charset=UTF-8\r\n";
        $emailBody .= "X-Mailer: PHP/" . phpversion() . "\r\n";
        if (isset($options['priority'])) {
            $emailBody .= "X-Priority: {$options['priority']}\r\n";
        }
        $emailBody .= "\r\n";
        $emailBody .= $message;
        $emailBody .= "\r\n.\r\n";

        fputs($socket, $emailBody);

        // Check response
        $response = fgets($socket, 515);
        if (!$this->checkSMTPResponse($response, '250')) {
            fclose($socket);
            return false;
        }

        // Send QUIT
        fputs($socket, "QUIT\r\n");
        fgets($socket, 515);
        fclose($socket);

        error_log("✓ Email sent successfully via SMTP to $to");
        return true;
    }

    /**
     * Send email via PHP mail() function
     */
    private function sendViaMail($to, $subject, $message, $options = []) {
        // Build headers
        $headers = $this->buildHeaders($options);

        // Check if HTML email
        $isHtml = isset($options['html']) && $options['html'] === true;
        if ($isHtml) {
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        } else {
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        }

        // Send email using PHP mail() function
        $sent = @mail($to, $subject, $message, $headers);

        if (!$sent) {
            $lastError = error_get_last();
            error_log("Mailer Error: Failed to send email to $to");
            if ($lastError) {
                error_log("PHP Error: " . $lastError['message']);
            }
            return false;
        }

        error_log("✓ Email sent successfully via mail() to $to");
        return true;
    }

    /**
     * Check SMTP response code
     */
    private function checkSMTPResponse($response, $expectedCode) {
        $code = substr($response, 0, 3);
        if ($code !== $expectedCode) {
            error_log("SMTP Error: Expected $expectedCode, got $code - $response");
            return false;
        }
        return true;
    }

    /**
     * Build email headers
     */
    private function buildHeaders($options = []) {
        $headers = [];

        // From header
        $headers[] = "From: {$this->fromName} <{$this->fromEmail}>";

        // Reply-To
        $replyTo = $options['reply_to'] ?? $this->fromEmail;
        $headers[] = "Reply-To: $replyTo";

        // X-Mailer
        $headers[] = "X-Mailer: PHP/" . phpversion();

        // MIME Version
        $headers[] = "MIME-Version: 1.0";

        // Priority
        if (isset($options['priority'])) {
            $headers[] = "X-Priority: {$options['priority']}";
        }

        return implode("\r\n", $headers) . "\r\n";
    }

    /**
     * Wrap plain message in HTML template
     */
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
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #1b2f73 0%, #0f1c49 100%); padding: 30px; text-align: center; border-radius: 8px 8px 0 0;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 24px; font-weight: bold;">
                                🏢 ATIERA Finance
                            </h1>
                        </td>
                    </tr>
                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px 30px;">
                            ' . $message . '
                        </td>
                    </tr>
                    <!-- Footer -->
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

    /**
     * Send verification code email
     */
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
                    ⚠️ <strong>Important:</strong> This code will expire in <strong>5 minutes</strong>.
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

    /**
     * Test email configuration
     */
    public function testConnection() {
        $testEmail = Config::get('company.email') ?: 'admin@atiera.com';
        $subject = 'ATIERA Mailer Test';
        $message = 'This is a test email from ATIERA Financial Management System. If you receive this, email is working properly!';

        return $this->send($testEmail, $subject, $message);
    }
}
?>
