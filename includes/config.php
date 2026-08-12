<?php
/**
 * AI Chat Assistant Configuration
 * Dynamic Environment Secret Loader
 */

// Custom Environment Loader Function
if (!function_exists('loadEnv')) {
    function loadEnv($path) {
        if (!file_exists($path)) {
            return;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $key = trim($parts[0]);
                $val = trim($parts[1]);
                // Remove optional quotes
                $val = trim($val, "\"'");
                putenv("{$key}={$val}");
                $_ENV[$key] = $val;
                $_SERVER[$key] = $val;
            }
        }
    }
}

// Load env secrets from repo root
loadEnv(__DIR__ . '/../.env');

// Check for Gemini API key in env
$gemini_key = getenv('GEMINI_API_KEY') ?: ($_ENV['GEMINI_API_KEY'] ?? '');

if (!empty($gemini_key)) {
    define('AI_PROVIDER', 'gemini');
    define('AI_API_KEY', $gemini_key);
} else {
    define('AI_PROVIDER', 'fallback');
    define('AI_API_KEY', '');
}

// Optional model override
define('AI_MODEL_OVERRIDE', 'gemini-1.5-flash');
?>