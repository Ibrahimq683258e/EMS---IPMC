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

// Check for NVIDIA API key in env as primary
$nvidia_key = getenv('NVIDIA_API_KEY') ?: ($_ENV['NVIDIA_API_KEY'] ?? '');
$nvidia_model = getenv('NVIDIA_MODEL') ?: ($_ENV['NVIDIA_MODEL'] ?? 'nvidia/llama-3.1-nemotron-51b-instruct');
$nvidia_base_url = getenv('NVIDIA_BASE_URL') ?: ($_ENV['NVIDIA_BASE_URL'] ?? 'https://integrate.api.nvidia.com/v1');

if (!empty($nvidia_key)) {
    define('AI_PROVIDER', 'nvidia');
    define('AI_API_KEY', $nvidia_key);
    define('AI_MODEL_OVERRIDE', $nvidia_model);
    define('AI_BASE_URL', $nvidia_base_url);
} else {
    // If not found, check for Gemini
    $gemini_key = getenv('GEMINI_API_KEY') ?: ($_ENV['GEMINI_API_KEY'] ?? '');
    if (!empty($gemini_key)) {
        define('AI_PROVIDER', 'gemini');
        define('AI_API_KEY', $gemini_key);
        define('AI_MODEL_OVERRIDE', 'gemini-1.5-flash');
        define('AI_BASE_URL', '');
    } else {
        define('AI_PROVIDER', 'fallback');
        define('AI_API_KEY', '');
        define('AI_MODEL_OVERRIDE', '');
        define('AI_BASE_URL', '');
    }
}
?>