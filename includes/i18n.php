<?php
/**
 * ATIERA Financial Management System - Internationalization (i18n)
 * Multi-language support system
 */

class I18n {
    private static $instance = null;
    private $currentLanguage = 'en';
    private $translations = [];
    private $fallbackLanguage = 'en';
    private $languageDir;
    private $supportedLanguages = ['en', 'es', 'fr', 'de', 'zh', 'ja', 'ko'];

    private function __construct() {
        $this->languageDir = __DIR__ . '/../languages';

        // Ensure language directory exists
        if (!is_dir($this->languageDir)) {
            mkdir($this->languageDir, 0755, true);
        }

        // Detect user language preference
        $this->detectLanguage();

        // Load translations
        $this->loadTranslations();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Detect user's preferred language
     */
    private function detectLanguage() {
        // Check session first
        if (isset($_SESSION['user']['language'])) {
            $this->currentLanguage = $_SESSION['user']['language'];
            return;
        }

        // Check user preferences from database
        if (isset($_SESSION['user']['id'])) {
            try {
                $db = Database::getInstance()->getConnection();
                $stmt = $db->prepare("SELECT language FROM user_preferences WHERE user_id = ?");
                $stmt->execute([$_SESSION['user']['id']]);
                $prefs = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($prefs && $prefs['language']) {
                    $this->currentLanguage = $prefs['language'];
                    $_SESSION['user']['language'] = $this->currentLanguage;
                    return;
                }
            } catch (Exception $e) {
                // Continue with browser detection
            }
        }

        // Check browser language
        $this->detectBrowserLanguage();

        // Store in session
        $_SESSION['user']['language'] = $this->currentLanguage;
    }

    /**
     * Detect browser preferred language
     */
    private function detectBrowserLanguage() {
        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $languages = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);

            foreach ($languages as $lang) {
                $lang = trim(explode(';', $lang)[0]);
                $lang = explode('-', $lang)[0]; // Remove country code

                if (in_array($lang, $this->supportedLanguages)) {
                    $this->currentLanguage = $lang;
                    return;
                }
            }
        }

        // Default to English
        $this->currentLanguage = 'en';
    }

    /**
     * Load translations for current language
     */
    private function loadTranslations() {
        // Load fallback language first
        $this->loadLanguageFile($this->fallbackLanguage);

        // Load current language (will override fallback)
        if ($this->currentLanguage !== $this->fallbackLanguage) {
            $this->loadLanguageFile($this->currentLanguage);
        }
    }

    /**
     * Load a specific language file
     */
    private function loadLanguageFile($language) {
        $file = $this->languageDir . '/' . $language . '.php';

        if (file_exists($file)) {
            $translations = include $file;
            if (is_array($translations)) {
                $this->translations = array_merge($this->translations, $translations);
            }
        }
    }

    /**
     * Get translated text
     */
    public function translate($key, $params = [], $default = null) {
        $translation = $this->translations[$key] ?? $default ?? $key;

        // Replace parameters
        if (!empty($params)) {
            foreach ($params as $param => $value) {
                $translation = str_replace(':' . $param, $value, $translation);
                $translation = str_replace('{' . $param . '}', $value, $translation);
            }
        }

        return $translation;
    }

    /**
     * Get current language
     */
    public function getCurrentLanguage() {
        return $this->currentLanguage;
    }

    /**
     * Set current language
     */
    public function setLanguage($language) {
        if (in_array($language, $this->supportedLanguages)) {
            $this->currentLanguage = $language;
            $_SESSION['user']['language'] = $language;

            // Update user preference in database
            if (isset($_SESSION['user']['id'])) {
                try {
                    $db = Database::getInstance()->getConnection();
                    $stmt = $db->prepare("
                        INSERT INTO user_preferences (user_id, language)
                        VALUES (?, ?)
                        ON DUPLICATE KEY UPDATE language = ?
                    ");
                    $stmt->execute([$_SESSION['user']['id'], $language, $language]);
                } catch (Exception $e) {
                    // Continue without updating database
                }
            }

            // Reload translations
            $this->translations = [];
            $this->loadTranslations();

            return true;
        }

        return false;
    }

    /**
     * Get supported languages
     */
    public function getSupportedLanguages() {
        return $this->supportedLanguages;
    }

    /**
     * Get language display names
     */
    public function getLanguageNames() {
        return [
            'en' => 'English',
            'es' => 'Español',
            'fr' => 'Français',
            'de' => 'Deutsch',
            'zh' => '中文',
            'ja' => '日本語',
            'ko' => '한국어'
        ];
    }

    /**
     * Check if a language is supported
     */
    public function isLanguageSupported($language) {
        return in_array($language, $this->supportedLanguages);
    }

    /**
     * Get all translations for current language
     */
    public function getAllTranslations() {
        return $this->translations;
    }

    /**
     * Add or update a translation
     */
    public function setTranslation($key, $value, $language = null) {
        $lang = $language ?: $this->currentLanguage;
        $file = $this->languageDir . '/' . $lang . '.php';

        // Load existing translations
        $translations = [];
        if (file_exists($file)) {
            $translations = include $file;
            if (!is_array($translations)) {
                $translations = [];
            }
        }

        // Update translation
        $translations[$key] = $value;

        // Save to file
        $content = "<?php\nreturn " . var_export($translations, true) . ";\n?>";

        if (file_put_contents($file, $content) !== false) {
            // Reload translations if updating current language
            if ($lang === $this->currentLanguage) {
                $this->translations[$key] = $value;
            }
            return true;
        }

        return false;
    }

    /**
     * Get translation statistics
     */
    public function getTranslationStats() {
        $stats = [];

        foreach ($this->supportedLanguages as $lang) {
            $file = $this->languageDir . '/' . $lang . '.php';
            $count = 0;

            if (file_exists($file)) {
                $translations = include $file;
                if (is_array($translations)) {
                    $count = count($translations);
                }
            }

            $stats[$lang] = [
                'code' => $lang,
                'name' => $this->getLanguageNames()[$lang] ?? $lang,
                'translations' => $count,
                'file_exists' => file_exists($file)
            ];
        }

        return $stats;
    }

    /**
     * Export translations for a language
     */
    public function exportTranslations($language) {
        $file = $this->languageDir . '/' . $language . '.php';

        if (file_exists($file)) {
            $translations = include $file;
            if (is_array($translations)) {
                return json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            }
        }

        return false;
    }

    /**
     * Import translations for a language
     */
    public function importTranslations($language, $jsonData) {
        $translations = json_decode($jsonData, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($translations)) {
            return false;
        }

        $file = $this->languageDir . '/' . $language . '.php';
        $content = "<?php\nreturn " . var_export($translations, true) . ";\n?>";

        if (file_put_contents($file, $content) !== false) {
            // Reload translations if importing current language
            if ($language === $this->currentLanguage) {
                $this->translations = [];
                $this->loadTranslations();
            }
            return true;
        }

        return false;
    }

    /**
     * Get missing translations compared to fallback language
     */
    public function getMissingTranslations($language) {
        $fallbackFile = $this->languageDir . '/' . $this->fallbackLanguage . '.php';
        $langFile = $this->languageDir . '/' . $language . '.php';

        $fallbackTranslations = [];
        $langTranslations = [];

        if (file_exists($fallbackFile)) {
            $fallbackTranslations = include $fallbackFile;
        }

        if (file_exists($langFile)) {
            $langTranslations = include $langFile;
        }

        $missing = [];
        foreach ($fallbackTranslations as $key => $value) {
            if (!isset($langTranslations[$key])) {
                $missing[$key] = $value;
            }
        }

        return $missing;
    }
}

// Global translation function
function __($key, $params = [], $default = null) {
    return I18n::getInstance()->translate($key, $params, $default);
}

// Translation alias
function _t($key, $params = [], $default = null) {
    return __($key, $params, $default);
}

// Echo translation
function _e($key, $params = [], $default = null) {
    echo __($key, $params, $default);
}
?>
