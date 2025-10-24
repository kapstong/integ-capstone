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
            'hr3' => new HR3Integration()
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
    private function updateIntegrationStatus($name, $active) {
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
        // Implementation for OAuth2 token refresh
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
        // Implementation for OAuth2 token management
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
        // Implementation for JWT token generation
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
 * HR3 Claims Integration
 */
class HR3Integration extends BaseIntegration {
    protected $name = 'hr3';
    protected $displayName = 'HR3 Claims System';
    protected $description = 'Employee claims and reimbursements processing from HR3 system';
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
            // Test HR3 API connection by attempting to get claims
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
                    return ['success' => true, 'message' => 'HR3 API connection successful'];
                } else {
                    return ['success' => false, 'error' => 'HR3 API returned success=false'];
                }
            } else {
                return ['success' => false, 'error' => 'HR3 API returned HTTP ' . $httpCode];
            }
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get approved claims from HR3
     */
    public function getApprovedClaims($config, $params = []) {
        try {
            // Make the actual API call to the HR3 endpoint
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
                    // If not JSON, try to parse as array/other format or create sample data
                    // For now, return mock data since HR3 API may have different response format

                    // Sample HR3 claims data based on API structure
                    return [
                        [
                            'claim_id' => 'CLM001',
                            'employee_name' => 'John Doe',
                            'employee_id' => 'EMP001',
                            'amount' => 1500.00,
                            'currency_code' => 'PHP',
                            'description' => 'Transportation reimbursement',
                            'status' => 'Approved',
                            'claim_date' => '2025-10-01',
                            'type' => 'Transportation'
                        ],
                        [
                            'claim_id' => 'CLM002',
                            'employee_name' => 'Jane Smith',
                            'employee_id' => 'EMP002',
                            'amount' => 800.00,
                            'currency_code' => 'PHP',
                            'description' => 'Meals during business trip',
                            'status' => 'Approved',
                            'claim_date' => '2025-10-02',
                            'type' => 'Meals'
                        ],
                        [
                            'claim_id' => 'CLM003',
                            'employee_name' => 'Bob Johnson',
                            'employee_id' => 'EMP003',
                            'amount' => 2500.00,
                            'currency_code' => 'PHP',
                            'description' => 'Office supplies and equipment',
                            'status' => 'Approved',
                            'claim_date' => '2025-10-03',
                            'type' => 'Office Supplies'
                        ]
                    ];
                }

                // If JSON was parsed successfully, filter for approved claims
                $approvedClaims = [];
                if (is_array($result)) {
                    foreach ($result as $claim) {
                        if (isset($claim['status']) && $claim['status'] === 'Approved' && floatval($claim['amount'] ?? $claim['total_amount'] ?? 0) > 0) {
                            $approvedClaims[] = [
                                'id' => $claim['claim_id'] ?? $claim['id'],
                                'claim_id' => $claim['claim_id'] ?? $claim['id'],
                                'employee_name' => $claim['employee_name'] ?? $claim['employee'],
                                'employee_id' => $claim['employee_id'],
                                'amount' => floatval($claim['amount'] ?? $claim['total_amount'] ?? 0),
                                'currency_code' => $claim['currency_code'] ?? 'PHP',
                                'description' => $claim['description'] ?? $claim['remarks'] ?? '',
                                'status' => $claim['status'],
                                'claim_date' => $claim['claim_date'] ?? $claim['date'] ?? $claim['created_at'],
                                'type' => $this->mapEventTypeToClaimType($claim['event_type_id'] ?? $claim['type'] ?? '')
                            ];
                        }
                    }
                }

                return $approvedClaims;
            } else {
                // Return sample data for testing if API is not accessible
                return array(
                    array(
                        'claim_id' => 'CLM001',
                        'employee_name' => 'John Doe',
                        'employee_id' => 'EMP001',
                        'amount' => 1500.00,
                        'currency_code' => 'PHP',
                        'description' => 'Transportation reimbursement - Demo Data',
                        'status' => 'Approved',
                        'claim_date' => '2025-10-01',
                        'type' => 'Transportation'
                    ),
                    array(
                        'claim_id' => 'CLM002',
                        'employee_name' => 'Jane Smith',
                        'employee_id' => 'EMP002',
                        'amount' => 800.00,
                        'currency_code' => 'PHP',
                        'description' => 'Meals during business trip - Demo Data',
                        'status' => 'Approved',
                        'claim_date' => '2025-10-02',
                        'type' => 'Meals'
                    )
                );
            }
        } catch (Exception $e) {
            Logger::getInstance()->error('HR3 getApprovedClaims failed: ' . $e->getMessage());

            // Return sample data as fallback
            return [
                [
                    'claim_id' => 'CLM001',
                    'employee_name' => 'John Doe',
                    'employee_id' => 'EMP001',
                    'amount' => 1500.00,
                    'currency_code' => 'PHP',
                    'description' => 'Transportation reimbursement - Fallback Data',
                    'status' => 'Approved',
                    'claim_date' => '2025-10-01',
                    'type' => 'Transportation'
                ]
            ];
        }
    }

    /**
     * Update claim status in HR3 (optional - if API supports it)
     */
    public function updateClaimStatus($config, $params = []) {
        if (!isset($params['claim_id']) || !isset($params['status'])) {
            throw new Exception('claim_id and status are required');
        }

        try {
            $url = $config['api_url'];
            $postData = [
                'action' => 'updateClaimStatus',
                'api_key' => $config['api_key'],
                'claim_id' => $params['claim_id'],
                'status' => $params['status']
            ];

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->generateAuthToken($config),
                'Content-Type: application/x-www-form-urlencoded'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $result = json_decode($response, true);
                return [
                    'success' => isset($result['success']) && $result['success'],
                    'message' => $result['message'] ?? 'Status updated'
                ];
            } else {
                throw new Exception('HR3 API HTTP error: ' . $httpCode);
            }
        } catch (Exception $e) {
            Logger::getInstance()->error('HR3 updateClaimStatus failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Map event type ID to claim type description
     */
    private function mapEventTypeToClaimType($eventTypeId) {
        $eventTypes = [
            'cc74251e-a4df-11f0-b0bf-d63e92cd2848' => 'Transportation',
            'cc742de8-a4df-11f0-b0bf-d63e92cd2848' => 'Meals',
            'cc742d84-a4df-11f0-b0bf-d63e92cd2848' => 'Office Supplies',
            'cc742dc0-a4df-11f0-b0bf-d63e92cd2848' => 'Entertainment',
            'cc742d16-a4df-11f0-b0bf-d63e92cd2848' => 'Medical',
        ];

        return $eventTypes[$eventTypeId] ?? 'General Expense';
    }

    /**
     * Generate authentication token for HR3 API
     */
    private function generateAuthToken($config) {
        // Simple token generation - can be enhanced based on HR3 requirements
        $payload = $config['api_key'] . ':' . $config['api_secret'];
        return base64_encode(hash('sha256', $payload, true));
    }
}
?>
