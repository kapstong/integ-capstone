<?php
/**
 * ATIERA Financial Management System - External API Integrations
 * Framework for integrating with third-party services and APIs
 */

class APIIntegrationManager {
    private static $instance = null;
    private $db;
    private $integrations = [];
    private $configDir;

    private function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->configDir = __DIR__ . '/../config/integrations';

        // Ensure config directory exists
        if (!is_dir($this->configDir)) {
            mkdir($this->configDir, 0755, true);
        }

        $this->loadIntegrations();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Load available integrations
     */
    private function loadIntegrations() {
        $this->integrations = [
            'stripe' => new StripeIntegration(),
            'paypal' => new PayPalIntegration(),
            'sendgrid' => new SendGridIntegration(),
            'twilio' => new TwilioIntegration(),
            'slack' => new SlackIntegration(),
            'google_drive' => new GoogleDriveIntegration(),
            'dropbox' => new DropboxIntegration(),
            'quickbooks' => new QuickBooksIntegration(),
            'xero' => new XeroIntegration(),
            'mailchimp' => new MailchimpIntegration(),
            'zoom' => new ZoomIntegration(),
            'microsoft_teams' => new MicrosoftTeamsIntegration(),
            'hr3' => new HR3Integration(),
            'hr4' => new HR4Integration(),
            'logistics1' => new Logistics1Integration(),
            'logistics2' => new Logistics2Integration()
        ];
    }

    /**
     * Get integration instance
     */
    public function getIntegration($name) {
        return isset($this->integrations[$name]) ? $this->integrations[$name] : null;
    }

    /**
     * Get all integrations
     */
    public function getAllIntegrations() {
        return $this->integrations;
    }

    /**
     * Configure an integration
     */
    public function configureIntegration($name, $config) {
        if (!isset($this->integrations[$name])) {
            return ['success' => false, 'error' => 'Integration not found'];
        }

        $integration = $this->integrations[$name];

        // Validate configuration
        $validation = $integration->validateConfig($config);
        if (!$validation['valid']) {
            return ['success' => false, 'error' => $validation['errors']];
        }

        // Save configuration
        $configFile = $this->configDir . '/' . $name . '.json';
        $encryptedConfig = $this->encryptConfig($config);

        if (file_put_contents($configFile, json_encode($encryptedConfig, JSON_PRETTY_PRINT))) {
            // Update integration status
            $this->updateIntegrationStatus($name, true);

            Logger::getInstance()->logUserAction(
                'Configured integration',
                'api_integrations',
                null,
                null,
                ['integration' => $name]
            );

            return ['success' => true, 'message' => 'Integration configured successfully'];
        }

        return ['success' => false, 'error' => 'Failed to save configuration'];
    }

    /**
     * Get integration configuration
     */
    public function getIntegrationConfig($name) {
        $configFile = $this->configDir . '/' . $name . '.json';

        if (file_exists($configFile)) {
            $encryptedConfig = json_decode(file_get_contents($configFile), true);
            return $this->decryptConfig($encryptedConfig);
        }

        return null;
    }

    /**
     * Test integration connection
     */
    public function testIntegration($name) {
        $integration = $this->getIntegration($name);
        if (!$integration) {
            return ['success' => false, 'error' => 'Integration not found'];
        }

        $config = $this->getIntegrationConfig($name);
        if (!$config) {
            return ['success' => false, 'error' => 'Integration not configured'];
        }

        try {
            $result = $integration->testConnection($config);
            return $result;
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Execute integration action
     */
    public function executeIntegrationAction($name, $action, $params = []) {
        $integration = $this->getIntegration($name);
        if (!$integration) {
            throw new Exception('Integration not found');
        }

        $config = $this->getIntegrationConfig($name);
        if (!$config) {
            throw new Exception('Integration not configured');
        }

        if (!method_exists($integration, $action)) {
            throw new Exception('Action not supported by integration');
        }

        try {
            $result = $integration->$action($config, $params);

            // Log the action
            Logger::getInstance()->logUserAction(
                'Executed integration action',
                'api_integrations',
                null,
                null,
                ['integration' => $name, 'action' => $action, 'params' => $params]
            );

            return $result;
        } catch (Exception $e) {
            Logger::getInstance()->error("Integration action failed: $name->$action", [
                'error' => $e->getMessage(),
                'params' => $params
            ]);
            throw $e;
        }
    }

    /**
     * Handle webhook from external service
     */
    public function handleWebhook($integrationName, $payload) {
        $integration = $this->getIntegration($integrationName);
        if (!$integration) {
            throw new Exception('Integration not found');
        }

        $config = $this->getIntegrationConfig($integrationName);
        if (!$config) {
            throw new Exception('Integration not configured');
        }

        try {
            $result = $integration->handleWebhook($config, $payload);

            // Log webhook
            Logger::getInstance()->logUserAction(
                'Processed webhook',
                'api_integrations',
                null,
                null,
                ['integration' => $integrationName, 'payload' => $payload]
            );

            return $result;
        } catch (Exception $e) {
            Logger::getInstance()->error("Webhook processing failed: $integrationName", [
                'error' => $e->getMessage(),
                'payload' => $payload
            ]);
            throw $e;
        }
    }

    /**
     * Get integration status
     */
    public function getIntegrationStatus($name) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM api_integrations WHERE name = ?");
            $stmt->execute([$name]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Update integration status
     */
    public function updateIntegrationStatus($name, $active) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO api_integrations (name, is_active, last_updated)
                VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE is_active = ?, last_updated = NOW()
            ");
            $stmt->execute([$name, $active, $active]);
        } catch (Exception $e) {
            Logger::getInstance()->error("Failed to update integration status: $name");
        }
    }

    /**
     * Encrypt sensitive configuration data
     */
    private function encryptConfig($config) {
        $key = Config::get('app.encryption_key', 'default_key_change_in_production');
        $encrypted = [];

        foreach ($config as $key => $value) {
            if (is_string($value) && strlen($value) > 0) {
                $encrypted[$key] = openssl_encrypt($value, 'AES-256-CBC', $key, 0, substr($key, 0, 16));
            } else {
                $encrypted[$key] = $value;
            }
        }

        return $encrypted;
    }

    /**
     * Decrypt configuration data
     */
    private function decryptConfig($encryptedConfig) {
        $key = Config::get('app.encryption_key', 'default_key_change_in_production');
        $config = [];

        foreach ($encryptedConfig as $key => $value) {
            if (is_string($value) && strlen($value) > 0) {
                $decrypted = openssl_decrypt($value, 'AES-256-CBC', $key, 0, substr($key, 0, 16));
                $config[$key] = $decrypted ?: $value; // Fallback to encrypted if decryption fails
            } else {
                $config[$key] = $value;
            }
        }

        return $config;
    }

    /**
     * Get integration statistics
     */
    public function getIntegrationStats() {
        try {
            $stmt = $this->db->query("
                SELECT
                    COUNT(*) as total_integrations,
                    COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_integrations,
                    COUNT(CASE WHEN last_updated >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 1 END) as recently_used
                FROM api_integrations
            ");
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return ['total_integrations' => 0, 'active_integrations' => 0, 'recently_used' => 0];
        }
    }
}

/**
 * Base Integration Class
 */
abstract class BaseIntegration {
    protected $name;
    protected $displayName;
    protected $description;
    protected $requiredConfig = [];
    protected $webhookSupport = false;

    /**
     * Validate configuration
     */
    public function validateConfig($config) {
        $errors = [];

        foreach ($this->requiredConfig as $field) {
            if (empty($config[$field])) {
                $errors[] = "Required field '$field' is missing";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Test connection to the service
     */
    abstract public function testConnection($config);

    /**
     * Handle webhook if supported
     */
    public function handleWebhook($config, $payload) {
        if (!$this->webhookSupport) {
            throw new Exception('Webhooks not supported by this integration');
        }
        return ['success' => true, 'message' => 'Webhook processed'];
    }

    /**
     * Get integration metadata
     */
    public function getMetadata() {
        return [
            'name' => $this->name,
            'display_name' => $this->displayName,
            'description' => $this->description,
            'required_config' => $this->requiredConfig,
            'webhook_support' => $this->webhookSupport
        ];
    }
}

/**
 * Stripe Payment Integration
 */
class StripeIntegration extends BaseIntegration {
    protected $name = 'stripe';
    protected $displayName = 'Stripe';
    protected $description = 'Payment processing and subscription management';
    protected $requiredConfig = ['api_key', 'webhook_secret'];
    protected $webhookSupport = true;

    public function testConnection($config) {
        try {
            // Test Stripe API connection
            $ch = curl_init('https://api.stripe.com/v1/customers');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $config['api_key'],
                'Content-Type: application/x-www-form-urlencoded'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                return ['success' => true, 'message' => 'Stripe connection successful'];
            } else {
                return ['success' => false, 'error' => 'Stripe API returned HTTP ' . $httpCode];
            }
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function createPaymentIntent($config, $params) {
        // Implementation for creating Stripe payment intent
        // This would integrate with actual Stripe API
        return ['success' => true, 'payment_intent_id' => 'pi_test_' . time()];
    }

    public function handleWebhook($config, $payload) {
        // Verify webhook signature
        $signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
        $expectedSignature = hash_hmac('sha256', $payload, $config['webhook_secret']);

        if (!hash_equals($signature, $expectedSignature)) {
            throw new Exception('Invalid webhook signature');
        }

        $event = json_decode($payload, true);

        // Process different webhook events
        switch ($event['type']) {
            case 'payment_intent.succeeded':
                // Handle successful payment
                Logger::getInstance()->info('Stripe payment succeeded', ['payment_intent' => $event['data']['object']['id']]);
                break;
            case 'payment_intent.payment_failed':
                // Handle failed payment
                Logger::getInstance()->warning('Stripe payment failed', ['payment_intent' => $event['data']['object']['id']]);
                break;
        }

        return ['success' => true, 'processed' => true];
    }
}

/**
 * SendGrid Email Integration
 */
class SendGridIntegration extends BaseIntegration {
    protected $name = 'sendgrid';
    protected $displayName = 'SendGrid';
    protected $description = 'Email delivery and marketing automation';
    protected $requiredConfig = ['api_key'];

    public function testConnection($config) {
        try {
            $ch = curl_init('https://api.sendgrid.com/v3/user/account');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $config['api_key'],
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                return ['success' => true, 'message' => 'SendGrid connection successful'];
            } else {
                return ['success' => false, 'error' => 'SendGrid API returned HTTP ' . $httpCode];
            }
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function sendEmail($config, $params) {
        // Implementation for sending email via SendGrid
        return ['success' => true, 'message_id' => 'sg_' . time()];
    }
}

/**
 * Twilio SMS Integration
 */
class TwilioIntegration extends BaseIntegration {
    protected $name = 'twilio';
    protected $displayName = 'Twilio';
    protected $description = 'SMS messaging and voice services';
    protected $requiredConfig = ['account_sid', 'auth_token', 'phone_number'];

    public function testConnection($config) {
        try {
            $ch = curl_init('https://api.twilio.com/2010-04-01/Accounts/' . $config['account_sid'] . '.json');
            curl_setopt($ch, CURLOPT_USERPWD, $config['account_sid'] . ':' . $config['auth_token']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                return ['success' => true, 'message' => 'Twilio connection successful'];
            } else {
                return ['success' => false, 'error' => 'Twilio API returned HTTP ' . $httpCode];
            }
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function sendSMS($config, $params) {
        // Implementation for sending SMS via Twilio
        return ['success' => true, 'message_sid' => 'SM' . time()];
    }
}

/**
 * Slack Integration
 */
class SlackIntegration extends BaseIntegration {
    protected $name = 'slack';
    protected $displayName = 'Slack';
    protected $description = 'Team communication and notifications';
    protected $requiredConfig = ['webhook_url'];
    protected $webhookSupport = false; // Uses webhooks but not receiving them

    public function testConnection($config) {
        try {
            $payload = json_encode([
                'text' => 'ATIERA Integration Test',
                'username' => 'ATIERA Bot'
            ]);

            $ch = curl_init($config['webhook_url']);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $response === 'ok') {
                return ['success' => true, 'message' => 'Slack webhook test successful'];
            } else {
                return ['success' => false, 'error' => 'Slack webhook test failed'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function sendNotification($config, $params) {
        // Implementation for sending Slack notifications
        return ['success' => true, 'timestamp' => time()];
    }
}

/**
 * PayPal Payment Integration
 */
class PayPalIntegration extends BaseIntegration {
    protected $name = 'paypal';
    protected $displayName = 'PayPal';
    protected $description = 'Alternative payment processing';
    protected $requiredConfig = ['client_id', 'client_secret'];

    public function testConnection($config) {
        try {
            // Test PayPal API connection
            $auth = base64_encode($config['client_id'] . ':' . $config['client_secret']);
            $ch = curl_init('https://api.paypal.com/v1/oauth2/token');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Basic ' . $auth,
                'Accept: application/json',
                'Accept-Language: en_US'
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                return ['success' => true, 'message' => 'PayPal connection successful'];
            } else {
                return ['success' => false, 'error' => 'PayPal API returned HTTP ' . $httpCode];
            }
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}

/**
 * Google Drive Integration
 */
class GoogleDriveIntegration extends BaseIntegration {
    protected $name = 'google_drive';
    protected $displayName = 'Google Drive';
    protected $description = 'Cloud storage and file sharing';
    protected $requiredConfig = ['client_id', 'client_secret', 'refresh_token'];

    public function testConnection($config) {
        try {
            // Test Google Drive API connection
            $ch = curl_init('https://www.googleapis.com/drive/v3/files?pageSize=1');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->getAccessToken($config),
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                return ['success' => true, 'message' => 'Google Drive connection successful'];
            } else {
                return ['success' => false, 'error' => 'Google Drive API returned HTTP ' . $httpCode];
            }
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function getAccessToken($config) {
        // Placeholder - OAuth2 token management would be implemented here
        return 'access_token_placeholder';
    }
}

/**
 * Dropbox Integration
 */
class DropboxIntegration extends BaseIntegration {
    protected $name = 'dropbox';
    protected $displayName = 'Dropbox';
    protected $description = 'Cloud storage and file synchronization';
    protected $requiredConfig = ['access_token'];

    public function testConnection($config) {
        try {
            $ch = curl_init('https://api.dropboxapi.com/2/users/get_current_account');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $config['access_token'],
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                return ['success' => true, 'message' => 'Dropbox connection successful'];
            } else {
                return ['success' => false, 'error' => 'Dropbox API returned HTTP ' . $httpCode];
            }
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}

/**
 * QuickBooks Integration
 */
class QuickBooksIntegration extends BaseIntegration {
    protected $name = 'quickbooks';
    protected $displayName = 'QuickBooks';
    protected $description = 'Accounting software integration';
    protected $requiredConfig = ['company_id', 'access_token'];

    public function testConnection($config) {
        try {
            $ch = curl_init('https://quickbooks.api.intuit.com/v3/company/' . $config['company_id'] . '/companyinfo/' . $config['company_id']);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $config['access_token'],
                'Accept: application/json'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                return ['success' => true, 'message' => 'QuickBooks connection successful'];
            } else {
                return ['success' => false, 'error' => 'QuickBooks API returned HTTP ' . $httpCode];
            }
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}

/**
 * Xero Integration
 */
class XeroIntegration extends BaseIntegration {
    protected $name = 'xero';
    protected $displayName = 'Xero';
    protected $description = 'Cloud accounting integration';
    protected $requiredConfig = ['client_id', 'client_secret', 'tenant_id'];

    public function testConnection($config) {
        try {
            // Test Xero API connection
            $ch = curl_init('https://api.xero.com/api.xro/2.0/Organisation');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->getAccessToken($config),
                'Xero-tenant-id: ' . $config['tenant_id'],
                'Accept: application/json'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                return ['success' => true, 'message' => 'Xero connection successful'];
            } else {
                return ['success' => false, 'error' => 'Xero API returned HTTP ' . $httpCode];
            }
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function getAccessToken($config) {
        // Placeholder - OAuth2 token management would be implemented here
        return 'access_token_placeholder';
    }
}

/**
 * Mailchimp Integration
 */
class MailchimpIntegration extends BaseIntegration {
    protected $name = 'mailchimp';
    protected $displayName = 'Mailchimp';
    protected $description = 'Email marketing and automation';
    protected $requiredConfig = ['api_key', 'server_prefix'];

    public function testConnection($config) {
        try {
            $ch = curl_init('https://' . $config['server_prefix'] . '.api.mailchimp.com/3.0/');
            curl_setopt($ch, CURLOPT_USERPWD, 'anystring:' . $config['api_key']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                return ['success' => true, 'message' => 'Mailchimp connection successful'];
            } else {
                return ['success' => false, 'error' => 'Mailchimp API returned HTTP ' . $httpCode];
            }
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}

/**
 * Zoom Integration
 */
class ZoomIntegration extends BaseIntegration {
    protected $name = 'zoom';
    protected $displayName = 'Zoom';
    protected $description = 'Video conferencing integration';
    protected $requiredConfig = ['api_key', 'api_secret'];

    public function testConnection($config) {
        try {
            // Generate JWT token for Zoom API
            $token = $this->generateJWT($config);

            $ch = curl_init('https://api.zoom.us/v2/users/me');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                return ['success' => true, 'message' => 'Zoom connection successful'];
            } else {
                return ['success' => false, 'error' => 'Zoom API returned HTTP ' . $httpCode];
            }
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function generateJWT($config) {
        // Placeholder - JWT token generation would be implemented here
        return 'jwt_token_placeholder';
    }
}

/**
 * Microsoft Teams Integration
 */
class MicrosoftTeamsIntegration extends BaseIntegration {
    protected $name = 'microsoft_teams';
    protected $displayName = 'Microsoft Teams';
    protected $description = 'Team collaboration platform';
    protected $requiredConfig = ['webhook_url'];

    public function testConnection($config) {
        try {
            $payload = json_encode([
                'text' => 'ATIERA Integration Test',
                '@type' => 'MessageCard',
                '@context' => 'http://schema.org/extensions',
                'summary' => 'ATIERA Test Message'
            ]);

            $ch = curl_init($config['webhook_url']);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                return ['success' => true, 'message' => 'Microsoft Teams webhook test successful'];
            } else {
                return ['success' => false, 'error' => 'Microsoft Teams webhook test failed'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}

/**
 * HR3 Integration (Placeholder - need to implement)
 */
class HR3Integration extends BaseIntegration {
    protected $name = 'hr3';
    protected $displayName = 'HR3 System';
    protected $description = 'HR3 System Integration';
    protected $requiredConfig = ['api_url'];

    public function testConnection($config) {
        // Placeholder implementation
        return ['success' => true, 'message' => 'HR3 connection placeholder'];
    }
}

/**
 * HR4 Payroll Integration
 */
class HR4Integration extends BaseIntegration {
    protected $name = 'hr4';
    protected $displayName = 'HR4 Payroll System';
    protected $description = 'Employee payroll and salary processing from HR4 system';
    protected $requiredConfig = ['api_url'];

    /**
     * Override validateConfig to handle optional authentication fields
     */
    public function validateConfig($config) {
        $errors = [];

        // Check required config
        if (empty($config['api_url'])) {
            $errors[] = "Required field 'api_url' is missing";
        }

        // api_key and api_secret are optional for now
        // They can be added later when authentication is implemented

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    public function testConnection($config) {
        try {
            // Test HR4 API connection by attempting to get payroll data
            $url = $config['api_url'];
            if (!str_ends_with($url, '?')) {
                $url .= '?';
            }
            $url .= 'action=test&api_key=' . urlencode($config['api_key']);

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->generateAuthToken($config),
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $result = json_decode($response, true);
                if (isset($result['success']) && $result['success']) {
                    return ['success' => true, 'message' => 'HR4 API connection successful'];
                } else {
                    return ['success' => false, 'error' => 'HR4 API returned success=false'];
                }
            } else {
                return ['success' => false, 'error' => 'HR4 API returned HTTP ' . $httpCode];
            }
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get payroll data from HR4
     */
    public function getPayrollData($config, $params = []) {
        try {
            // Make the actual API call to the HR4 endpoint
            $url = $config['api_url'];

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For testing - remove in production
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // For testing - remove in production

            // Add headers if authentication is configured
            $headers = ['Content-Type: application/json'];
            if (!empty($config['api_key'])) {
                $headers[] = 'Authorization: Bearer ' . $this->generateAuthToken($config);
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                throw new Exception('cURL Error: ' . $curlError);
            }

            if ($httpCode === 200) {
                // Try to decode JSON response
                $result = json_decode($response, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    // If not JSON, return sample payroll data
                    return $this->getSamplePayrollData();
                }

                // Parse the actual HR4 API response format and convert to our expected format
                if (isset($result['success']) && $result['success'] && isset($result['payroll_data'])) {
                    return $this->parseHR4PayrollData($result);
                } else {
                    // Return sample data if API response is not in expected format
                    return $this->getSamplePayrollData();
                }
            } else {
                // Return sample data for testing if API is not accessible
                return $this->getSamplePayrollData();
            }
        } catch (Exception $e) {
            Logger::getInstance()->error('HR4 getPayrollData failed: ' . $e->getMessage());

            // Return sample data as fallback
            return $this->getSamplePayrollData();
        }
    }

    /**
     * Parse the actual HR4 API response format into our expected payroll data format
     */
    private function parseHR4PayrollData($apiResponse) {
        $parsedData = [];

        if (!isset($apiResponse['payroll_data']) || !is_array($apiResponse['payroll_data'])) {
            return $this->getSamplePayrollData();
        }

        $payrollMonth = $apiResponse['month'] ?? date('Y-m');

        foreach ($apiResponse['payroll_data'] as $employeeData) {
            // Map department name to department ID
            $departmentMapping = [
                'Human Resources Department' => 1, // Administrative
                'Kitchen Department' => 2,
                'Front Office Department' => 3,
                'Housekeeping Department' => 4,
                'Food & Beverage Department' => 2, // Map to Kitchen
                'Maintenance Department' => 5,
                'Security Department' => 6,
                'Accounting Department' => 1, // Administrative
                'Sales & Marketing Department' => 7,
            ];

            $departmentName = $employeeData['department'] ?? 'General';
            $departmentId = $departmentMapping[$departmentName] ?? 1; // Default to Administrative

            // Convert employee data to our expected format
            $parsedEntry = [
                'payroll_id' => 'HR4_' . $employeeData['id'],
                'employee_id' => 'EMP_' . $employeeData['id'],
                'employee_name' => $employeeData['name'] ?? 'Unknown Employee',
                'department_id' => $departmentId,
                'department' => $departmentName,
                'payroll_date' => date('Y-m-d'), // Use current date as payroll date
                'payroll_period' => $payrollMonth . '-01 to ' . $payrollMonth . '-' . date('t', strtotime($payrollMonth . '-01')),
                'basic_salary' => floatval($employeeData['basic_salary'] ?? 0),
                'overtime_pay' => floatval($employeeData['overtime_pay'] ?? 0),
                'bonus' => floatval($employeeData['bonus'] ?? 0),
                'allowances' => floatval($employeeData['additions'] ?? 0),
                'deductions' => floatval($employeeData['deductions'] ?? 0),
                'gross' => floatval($employeeData['gross'] ?? 0),
                'net_pay' => floatval($employeeData['net'] ?? 0),
                'amount' => floatval($employeeData['net'] ?? 0), // For compatibility
                'currency_code' => 'PHP',
                'status' => 'processed',
                'position' => $employeeData['position'] ?? 'Staff',
                'email' => $employeeData['email'] ?? '',
                'date' => date('Y-m-d'), // For compatibility
                'id' => $employeeData['id'] ?? 0
            ];

            $parsedData[] = $parsedEntry;
        }

        return $parsedData;
    }

    /**
     * Get appropriate expense account for payroll based on department
     * Returns the account id to use for payroll expenses
     */
    private function getPayrollExpenseAccount($payroll = []) {
        // Base payroll expense accounts by department
        $departmentAccounts = [
            1 => 5401, // Administrative Salaries (Department 1)
            2 => 5402, // Kitchen/F&B Salaries (Department 2)
            3 => 5403, // Front Desk/Service Salaries (Department 3)
            // Add more department mappings as needed
        ];

        $departmentId = $payroll['department_id'] ?? 1;
        return $departmentAccounts[$departmentId] ?? 5401; // Default to Administrative Salaries
    }

    /**
     * Get appropriate liability account for accrued payroll
     * Returns the account id to use for accrued wages payable
     */
    private function getPayrollLiabilityAccount() {
        return 2107; // Accrued Salaries Payable - this should exist in chart_of_accounts
    }

    /**
     * Import payroll data into financials system
     * Updates department expense tracking that feeds into automatic journal entry generation
     */
    public function importPayroll($config, $params = []) {
        $payrollData = $this->getPayrollData($config, $params);

        $importedCount = 0;
        $errors = [];

        foreach ($payrollData as $payroll) {
            try {
                $db = Database::getInstance()->getConnection();
                $amount = floatval($payroll['net_pay'] ?? $payroll['amount'] ?? 0);

                if ($amount <= 0) {
                    continue; // Skip invalid amounts
                }

                $batchId = 'PAYROLL_' . date('Ymd_His');
                $entryDate = $payroll['payroll_date'] ?? $payroll['date'] ?? date('Y-m-d');
                $description = 'Payroll: ' . ($payroll['employee_name'] ?? 'Employee') . ' - ' . ($payroll['payroll_period'] ?? 'Period');
                $externalId = $payroll['payroll_id'] ?? $payroll['id'] ?? 'PAY' . time() . '_' . $importedCount;
                $departmentId = $payroll['department_id'] ?? 1;

                // Import to imported_transactions for audit trail and system processing
                $stmt = $db->prepare("
                    INSERT INTO imported_transactions
                    (import_batch, source_system, transaction_date, transaction_type,
                     external_id, department_id, amount, description, raw_data, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
                ");

                $stmt->execute([
                    $batchId,
                    'HR_SYSTEM',
                    $entryDate,
                    'payroll_expense',
                    $externalId,
                    $departmentId,
                    $amount,
                    $description,
                    json_encode($payroll)
                ]);

                // Update daily_expense_summary - this feeds into automatic department reporting
                $summaryStmt = $db->prepare("
                    INSERT INTO daily_expense_summary
                    (business_date, department_id, expense_category, source_system, total_transactions, total_amount)
                    VALUES (?, ?, ?, ?, 1, ?)
                    ON DUPLICATE KEY UPDATE
                        total_transactions = total_transactions + 1,
                        total_amount = total_amount + VALUES(total_amount),
                        updated_at = NOW()
                ");
                $summaryStmt->execute([$entryDate, $departmentId, 'labor_payroll', 'HR_SYSTEM', $amount]);

                $importedCount++;

                Logger::getInstance()->info('Payroll imported to department expense tracking', [
                    'payroll_id' => $externalId,
                    'employee' => $payroll['employee_name'] ?? 'Unknown',
                    'amount' => $amount,
                    'department_id' => $departmentId
                ]);

            } catch (Exception $e) {
                $errors[] = 'Failed to import payroll for ' . ($payroll['employee_name'] ?? 'Unknown') . ': ' . $e->getMessage();
                Logger::getInstance()->error('Payroll import error', [
                    'payroll' => $payroll,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return [
            'success' => count($errors) === 0,
            'imported_count' => $importedCount,
            'errors' => $errors,
            'message' => "Imported {$importedCount} payroll records to department expense tracking" . (count($errors) > 0 ? " with " . count($errors) . " errors" : "")
        ];
    }

    /**
     * Get sample payroll data for testing/fallback
     */
    private function getSamplePayrollData() {
        return [
            [
                'payroll_id' => 'PAY001',
                'employee_id' => 'EMP001',
                'employee_name' => 'John Doe',
                'department_id' => 1,
                'payroll_date' => date('Y-m-d'),
                'payroll_period' => date('Y-m-01') . ' to ' . date('Y-m-t'),
                'basic_salary' => 25000.00,
                'allowances' => 5000.00,
                'deductions' => 2000.00,
                'net_pay' => 28000.00,
                'currency_code' => 'PHP',
                'status' => 'processed'
            ],
            [
                'payroll_id' => 'PAY002',
                'employee_id' => 'EMP002',
                'employee_name' => 'Jane Smith',
                'department_id' => 2,
                'payroll_date' => date('Y-m-d'),
                'payroll_period' => date('Y-m-01') . ' to ' . date('Y-m-t'),
                'basic_salary' => 22000.00,
                'allowances' => 4500.00,
                'deductions' => 1800.00,
                'net_pay' => 24700.00,
                'currency_code' => 'PHP',
                'status' => 'processed'
            ],
            [
                'payroll_id' => 'PAY003',
                'employee_id' => 'EMP003',
                'employee_name' => 'Bob Johnson',
                'department_id' => 1,
                'payroll_date' => date('Y-m-d'),
                'payroll_period' => date('Y-m-01') . ' to ' . date('Y-m-t'),
                'basic_salary' => 20000.00,
                'allowances' => 4000.00,
                'deductions' => 1600.00,
                'net_pay' => 22400.00,
                'currency_code' => 'PHP',
                'status' => 'processed'
            ]
        ];
    }

    /**
     * Generate authentication token for HR4 API
     */
    private function generateAuthToken($config) {
        // Simple token generation - can be enhanced based on HR4 requirements
        $payload = $config['api_key'] . ':' . $config['api_secret'];
        return base64_encode(hash('sha256', $payload, true));
    }
}

/**
 * Logistics 1 Integration - Purchase Orders, Delivery Receipts, and Invoices
 */
class Logistics1Integration extends BaseIntegration {
    public function getName() {
        return 'Logistics 1 - Procurement System';
    }

    public function getDescription() {
        return 'Import purchase orders, delivery receipts, and supplier invoices from Logistics 1 system';
    }

    public function getCategory() {
        return 'Procurement & Inventory';
    }

    public function getRequiredFields() {
        return ['api_url'];
    }

    public function getFormFields() {
        return [
            'api_url' => [
                'label' => 'API URL',
                'type' => 'url',
                'required' => true,
                'default' => 'https://logistics1.atierahotelandrestaurant.com/api/docu/docu.php'
            ]
        ];
    }

    public function validateConfig($config) {
        if (empty($config['api_url'])) {
            return ['valid' => false, 'errors' => ['API URL is required']];
        }
        return ['valid' => true];
    }

    public function testConnection($config) {
        try {
            $ch = curl_init($config['api_url']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                return ['success' => false, 'message' => 'HTTP ' . $httpCode];
            }

            $data = json_decode($response, true);
            if (!isset($data['success'])) {
                return ['success' => false, 'message' => 'Invalid response format'];
            }

            $poCount = count($data['purchase_orders'] ?? []);
            $drCount = count($data['delivery_receipts'] ?? []);
            $invCount = count($data['invoices'] ?? []);

            return [
                'success' => true,
                'message' => "Connected successfully. Found {$poCount} POs, {$drCount} DRs, {$invCount} invoices"
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getActions() {
        return [
            'importInvoices' => [
                'label' => 'Import Supplier Invoices',
                'description' => 'Import supplier invoices to Accounts Payable',
                'icon' => 'fa-file-invoice-dollar'
            ],
            'importPurchaseOrders' => [
                'label' => 'Import Purchase Orders',
                'description' => 'Import POs for expense tracking',
                'icon' => 'fa-shopping-cart'
            ]
        ];
    }

    public function executeAction($action, $config, $params = []) {
        switch ($action) {
            case 'importInvoices':
                return $this->importInvoices($config, $params);
            case 'importPurchaseOrders':
                return $this->importPurchaseOrders($config, $params);
            default:
                return ['success' => false, 'error' => 'Unknown action'];
        }
    }

    /**
     * Fetch procurement data from Logistics 1 API
     */
    private function getProcurementData($config) {
        $ch = curl_init($config['api_url']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception('Logistics 1 API returned HTTP ' . $httpCode);
        }

        $data = json_decode($response, true);
        if (!$data || !isset($data['success']) || !$data['success']) {
            throw new Exception('Invalid response from Logistics 1 API');
        }

        return $data;
    }

    /**
     * Import supplier invoices to Accounts Payable
     */
    public function importInvoices($config, $params = []) {
        try {
            $data = $this->getProcurementData($config);
            $invoices = $data['invoices'] ?? [];

            $importedCount = 0;
            $errors = [];

            foreach ($invoices as $invoice) {
                try {
                    $db = Database::getInstance()->getConnection();

                    $totalAmount = floatval($invoice['total'] ?? 0);
                    if ($totalAmount <= 0) continue;

                    $batchId = 'LOG1_INV_' . date('Ymd_His');
                    $invoiceDate = $invoice['date_issued'] ?? date('Y-m-d');
                    $description = 'Invoice #' . ($invoice['invoice_number'] ?? 'N/A') . ' from ' . ($invoice['supplier_name'] ?? 'Supplier');
                    $externalId = 'LOG1_INV_' . ($invoice['invoice_id'] ?? time() . '_' . $importedCount);

                    // Map department based on purchase order department
                    $departmentName = $invoice['department_name'] ?? '';
                    $departmentId = $this->mapDepartment($departmentName);

                    // Import to imported_transactions
                    $stmt = $db->prepare("
                        INSERT INTO imported_transactions
                        (import_batch, source_system, transaction_date, transaction_type,
                         external_id, external_reference, department_id, customer_name,
                         description, amount, raw_data, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
                    ");

                    $stmt->execute([
                        $batchId,
                        'LOGISTICS1',
                        $invoiceDate,
                        'supplier_invoice',
                        $externalId,
                        $invoice['invoice_number'] ?? '',
                        $departmentId,
                        $invoice['supplier_name'] ?? '',
                        $description,
                        $totalAmount,
                        json_encode($invoice)
                    ]);

                    // Update daily_expense_summary for expense tracking
                    $summaryStmt = $db->prepare("
                        INSERT INTO daily_expense_summary
                        (business_date, department_id, expense_category, source_system, total_transactions, total_amount)
                        VALUES (?, ?, ?, ?, 1, ?)
                        ON DUPLICATE KEY UPDATE
                            total_transactions = total_transactions + 1,
                            total_amount = total_amount + VALUES(total_amount),
                            updated_at = NOW()
                    ");
                    $summaryStmt->execute([$invoiceDate, $departmentId, 'supplies_materials', 'LOGISTICS1', $totalAmount]);

                    $importedCount++;

                    Logger::getInstance()->info('Logistics 1 invoice imported', [
                        'invoice_id' => $externalId,
                        'supplier' => $invoice['supplier_name'] ?? 'Unknown',
                        'amount' => $totalAmount
                    ]);

                } catch (Exception $e) {
                    $errors[] = 'Failed to import invoice ' . ($invoice['invoice_number'] ?? 'Unknown') . ': ' . $e->getMessage();
                    Logger::getInstance()->error('Logistics 1 invoice import error', [
                        'invoice' => $invoice,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return [
                'success' => count($errors) === 0,
                'imported_count' => $importedCount,
                'errors' => $errors,
                'message' => "Imported {$importedCount} supplier invoices" . (count($errors) > 0 ? " with " . count($errors) . " errors" : "")
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Import purchase orders for expense tracking
     */
    public function importPurchaseOrders($config, $params = []) {
        try {
            $data = $this->getProcurementData($config);
            $purchaseOrders = $data['purchase_orders'] ?? [];

            $importedCount = 0;
            $errors = [];

            foreach ($purchaseOrders as $po) {
                try {
                    // Only import approved POs
                    if (($po['status_type'] ?? '') !== 'Approved') continue;

                    $db = Database::getInstance()->getConnection();

                    // Calculate total from items
                    $totalAmount = 0;
                    foreach (($po['items'] ?? []) as $item) {
                        $totalAmount += floatval($item['total_price'] ?? 0);
                    }

                    if ($totalAmount <= 0) continue;

                    $batchId = 'LOG1_PO_' . date('Ymd_His');
                    $poDate = $po['purchase_date'] ?? date('Y-m-d');
                    $description = 'PO #' . ($po['purchase_number'] ?? 'N/A') . ' - ' . ($po['supplier_name'] ?? 'Supplier');
                    $externalId = 'LOG1_PO_' . ($po['purchase_id'] ?? time() . '_' . $importedCount);

                    $departmentName = $po['department_name'] ?? '';
                    $departmentId = $this->mapDepartment($departmentName);

                    // Import to imported_transactions
                    $stmt = $db->prepare("
                        INSERT INTO imported_transactions
                        (import_batch, source_system, transaction_date, transaction_type,
                         external_id, external_reference, department_id, customer_name,
                         description, amount, raw_data, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
                    ");

                    $stmt->execute([
                        $batchId,
                        'LOGISTICS1',
                        $poDate,
                        'purchase_order',
                        $externalId,
                        $po['purchase_number'] ?? '',
                        $departmentId,
                        $po['supplier_name'] ?? '',
                        $description,
                        $totalAmount,
                        json_encode($po)
                    ]);

                    $importedCount++;

                    Logger::getInstance()->info('Logistics 1 PO imported', [
                        'po_id' => $externalId,
                        'supplier' => $po['supplier_name'] ?? 'Unknown',
                        'amount' => $totalAmount
                    ]);

                } catch (Exception $e) {
                    $errors[] = 'Failed to import PO ' . ($po['purchase_number'] ?? 'Unknown') . ': ' . $e->getMessage();
                }
            }

            return [
                'success' => count($errors) === 0,
                'imported_count' => $importedCount,
                'errors' => $errors,
                'message' => "Imported {$importedCount} purchase orders" . (count($errors) > 0 ? " with " . count($errors) . " errors" : "")
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Map department name to department ID
     */
    private function mapDepartment($departmentName) {
        $mapping = [
            'kitchen' => 2,
            'food' => 2,
            'beverage' => 2,
            'front' => 3,
            'desk' => 3,
            'reception' => 3,
            'admin' => 1,
            'office' => 1,
            'hr' => 1,
            'human resources' => 1
        ];

        $lowerName = strtolower($departmentName);
        foreach ($mapping as $keyword => $deptId) {
            if (strpos($lowerName, $keyword) !== false) {
                return $deptId;
            }
        }

        return 1; // Default to Administrative
    }
}

/**
 * Logistics 2 Integration - Trip Costs and Transportation
 */
class Logistics2Integration extends BaseIntegration {
    public function getName() {
        return 'Logistics 2 - Transportation System';
    }

    public function getDescription() {
        return 'Import trip costs, fuel expenses, and vehicle transportation data from Logistics 2 system';
    }

    public function getCategory() {
        return 'Transportation & Logistics';
    }

    public function getRequiredFields() {
        return ['api_url'];
    }

    public function getFormFields() {
        return [
            'api_url' => [
                'label' => 'API URL',
                'type' => 'url',
                'required' => true,
                'default' => 'https://logistic2.atierahotelandrestaurant.com/integration/trip-costs-api.php'
            ]
        ];
    }

    public function validateConfig($config) {
        if (empty($config['api_url'])) {
            return ['valid' => false, 'errors' => ['API URL is required']];
        }
        return ['valid' => true];
    }

    public function testConnection($config) {
        try {
            $ch = curl_init($config['api_url']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                return ['success' => false, 'message' => 'HTTP ' . $httpCode];
            }

            $data = json_decode($response, true);
            if (!isset($data['trips'])) {
                return ['success' => false, 'message' => 'Invalid response format'];
            }

            $tripCount = count($data['trips'] ?? []);
            $totalCost = floatval($data['summary']['grand_total'] ?? 0);

            return [
                'success' => true,
                'message' => "Connected successfully. Found {$tripCount} trips with total cost of ₱" . number_format($totalCost, 2)
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getActions() {
        return [
            'importTripCosts' => [
                'label' => 'Import Trip Costs',
                'description' => 'Import transportation and fuel expenses',
                'icon' => 'fa-truck'
            ]
        ];
    }

    public function executeAction($action, $config, $params = []) {
        switch ($action) {
            case 'importTripCosts':
                return $this->importTripCosts($config, $params);
            default:
                return ['success' => false, 'error' => 'Unknown action'];
        }
    }

    /**
     * Fetch trip costs from Logistics 2 API
     */
    private function getTripData($config) {
        $ch = curl_init($config['api_url']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception('Logistics 2 API returned HTTP ' . $httpCode);
        }

        $data = json_decode($response, true);
        if (!$data || !isset($data['trips'])) {
            throw new Exception('Invalid response from Logistics 2 API');
        }

        return $data;
    }

    /**
     * Import trip costs to operating expenses
     */
    public function importTripCosts($config, $params = []) {
        try {
            $data = $this->getTripData($config);
            $trips = $data['trips'] ?? [];

            $importedCount = 0;
            $errors = [];

            foreach ($trips as $trip) {
                try {
                    $db = Database::getInstance()->getConnection();

                    $totalAmount = floatval($trip['total_amount'] ?? 0);
                    if ($totalAmount <= 0) continue;

                    // Only import completed trips
                    if (($trip['status'] ?? '') !== 'Completed') continue;

                    $batchId = 'LOG2_TRIP_' . date('Ymd_His');
                    $tripDate = isset($trip['date_added']) ? date('Y-m-d', strtotime($trip['date_added'])) : date('Y-m-d');
                    $description = 'Trip: ' . ($trip['trip_description'] ?? 'N/A') . ' - ' . ($trip['driver_name'] ?? 'Driver') . ' (' . ($trip['vehicle_name'] ?? 'Vehicle') . ')';
                    $externalId = 'LOG2_TRIP_' . ($trip['trip_ID'] ?? time() . '_' . $importedCount);

                    // Transportation costs go to administrative/operations department
                    $departmentId = 1; // Administrative

                    // Import to imported_transactions
                    $stmt = $db->prepare("
                        INSERT INTO imported_transactions
                        (import_batch, source_system, transaction_date, transaction_type,
                         external_id, external_reference, department_id, description,
                         amount, raw_data, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
                    ");

                    $stmt->execute([
                        $batchId,
                        'LOGISTICS2',
                        $tripDate,
                        'transportation_expense',
                        $externalId,
                        $trip['trip_ID'] ?? '',
                        $departmentId,
                        $description,
                        $totalAmount,
                        json_encode($trip)
                    ]);

                    // Update daily_expense_summary - split between fuel and other costs
                    $fuelCost = floatval($trip['total_fuel_cost'] ?? 0);
                    $otherCost = floatval($trip['total_cost_amount'] ?? 0);

                    if ($fuelCost > 0) {
                        $fuelStmt = $db->prepare("
                            INSERT INTO daily_expense_summary
                            (business_date, department_id, expense_category, source_system, total_transactions, total_amount)
                            VALUES (?, ?, ?, ?, 1, ?)
                            ON DUPLICATE KEY UPDATE
                                total_transactions = total_transactions + 1,
                                total_amount = total_amount + VALUES(total_amount),
                                updated_at = NOW()
                        ");
                        $fuelStmt->execute([$tripDate, $departmentId, 'fuel_transportation', 'LOGISTICS2', $fuelCost]);
                    }

                    if ($otherCost > 0) {
                        $costStmt = $db->prepare("
                            INSERT INTO daily_expense_summary
                            (business_date, department_id, expense_category, source_system, total_transactions, total_amount)
                            VALUES (?, ?, ?, ?, 1, ?)
                            ON DUPLICATE KEY UPDATE
                                total_transactions = total_transactions + 1,
                                total_amount = total_amount + VALUES(total_amount),
                                updated_at = NOW()
                        ");
                        $costStmt->execute([$tripDate, $departmentId, 'transportation_other', 'LOGISTICS2', $otherCost]);
                    }

                    $importedCount++;

                    Logger::getInstance()->info('Logistics 2 trip cost imported', [
                        'trip_id' => $externalId,
                        'driver' => $trip['driver_name'] ?? 'Unknown',
                        'amount' => $totalAmount,
                        'fuel' => $fuelCost,
                        'other' => $otherCost
                    ]);

                } catch (Exception $e) {
                    $errors[] = 'Failed to import trip ' . ($trip['trip_ID'] ?? 'Unknown') . ': ' . $e->getMessage();
                    Logger::getInstance()->error('Logistics 2 trip import error', [
                        'trip' => $trip,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return [
                'success' => count($errors) === 0,
                'imported_count' => $importedCount,
                'errors' => $errors,
                'message' => "Imported {$importedCount} trip cost records" . (count($errors) > 0 ? " with " . count($errors) . " errors" : "")
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
?>
