<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Finance AI Chat Configuration
    |--------------------------------------------------------------------------
    |
    | Provider options:
    |   'gemini'      - Google Gemini API langsung (default, free tier)
    |   'openrouter'  - OpenRouter (support semua model: Gemini, Claude, GPT, dll)
    |
    */

    // === Provider ===

    'provider' => env('FINANCE_AI_PROVIDER', 'gemini'),

    // === Model ===

    // Untuk Gemini langsung: 'gemini-2.5-flash', 'gemini-2.0-flash', 'gemini-pro'
    // Untuk OpenRouter: 'google/gemini-2.5-flash', 'anthropic/claude-3.5-haiku', 'openai/gpt-4o-mini', dll
    'model' => env('FINANCE_AI_MODEL', 'gemini-2.5-flash'),

    // === API Keys ===

    // Gemini: dari Google AI Studio (https://aistudio.google.com)
    'gemini_api_key' => env('FINANCE_AI_GEMINI_KEY', env('GEMINI_API_KEY')),

    // OpenRouter: dari https://openrouter.ai/keys
    'openrouter_api_key' => env('FINANCE_AI_OPENROUTER_KEY', env('OPENROUTER_API_KEY')),

    // === Endpoints ===

    'gemini_base_url' => env('FINANCE_AI_GEMINI_URL', 'https://generativelanguage.googleapis.com/v1beta'),
    'openrouter_base_url' => env('FINANCE_AI_OPENROUTER_URL', 'https://openrouter.ai/api/v1'),

    // === Generation Config ===

    'temperature' => (float) env('FINANCE_AI_TEMPERATURE', 0.3),
    'max_tokens' => (int) env('FINANCE_AI_MAX_TOKENS', 2048),
    'timeout_seconds' => (int) env('FINANCE_AI_TIMEOUT', 60),

    // === OpenRouter Headers (optional) ===

    'openrouter_site_name' => env('FINANCE_AI_SITE_NAME', 'Mataram Petshop Finance'),
    'openrouter_site_url' => env('FINANCE_AI_SITE_URL', ''),

];
