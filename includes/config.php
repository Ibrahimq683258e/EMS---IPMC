<?php
/**
 * AI Chat Assistant Configuration
 * Define API credentials and selector modes.
 */

// Choose provider: 'gemini', 'openai', or 'fallback' (local hybrid keyword system)
define('AI_PROVIDER', 'fallback');

// API Key (Enter your Gemini or OpenAI API Key here if using them)
define('AI_API_KEY', '');

// Optional model override (e.g. 'gemini-1.5-flash', 'gpt-4o-mini')
define('AI_MODEL_OVERRIDE', '');
?>