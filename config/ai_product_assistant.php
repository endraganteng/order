<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Product Assistant Configuration
    |--------------------------------------------------------------------------
    |
    | Konfigurasi untuk dua fitur AI:
    | 1. AI Product Knowledge Enrichment (admin only)
    | 2. AI Product Chat (admin + waiter)
    |
    | Source of truth: Firebase Realtime Database
    | Vector index:    Supabase pgvector
    | Chat sessions:   MySQL (Laravel models)
    |
    */

    // === Gemini API ===

    'gemini_api_key' => env('GEMINI_API_KEY'),
    'gemini_embedding_model' => env('GEMINI_EMBEDDING_MODEL', 'gemini-embedding-001'),
    'gemini_chat_model' => env('GEMINI_CHAT_MODEL', 'gemini-2.5-flash'),
    'gemini_base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),

    // pgvector HNSW index hanya support max 2000 dim. gemini-embedding-001 default 3072.
    // Wajib pass outputDimensionality=768 di request body saat call embedContent.
    'gemini_embedding_dimension' => 768,

    // === Supabase pgvector ===

    'supabase_url' => env('SUPABASE_URL'),
    'supabase_service_role_key' => env('SUPABASE_SERVICE_ROLE_KEY'),
    'supabase_vector_table' => env('SUPABASE_VECTOR_TABLE', 'ai_product_vectors'),

    // === Web Research Provider ===

    // Pilihan:
    //   'gemini_grounded' - native Gemini Google Search (default, recommended)
    //   'disabled'        - skip research, extract dari nama+kategori produk saja
    'product_research_provider' => env('PRODUCT_RESEARCH_PROVIDER', 'gemini_grounded'),

    // === Firebase Paths ===

    // Configurable karena project ini pakai 'rack_products' bukan 'products' default.
    'firebase_products_path' => env('AI_FIREBASE_PRODUCTS_PATH', 'rack_products'),
    'firebase_knowledge_path' => env('AI_FIREBASE_KNOWLEDGE_PATH', 'product_knowledge_base'),

    // === Limits ===

    'chat_max_products' => (int) env('AI_PRODUCT_CHAT_MAX_PRODUCTS', 5),
    'chat_min_score' => (float) env('AI_PRODUCT_CHAT_MIN_SCORE', 160),
    'vector_top_k' => (int) env('AI_PRODUCT_VECTOR_TOP_K', 10),
    'enrichment_batch_limit' => (int) env('AI_PRODUCT_ENRICHMENT_BATCH_LIMIT', 20),
    'vector_sync_batch_limit' => (int) env('AI_PRODUCT_VECTOR_SYNC_BATCH_LIMIT', 50),

    // === HTTP Timeouts (seconds) ===

    'gemini_timeout_seconds' => (int) env('AI_GEMINI_TIMEOUT', 60),
    'supabase_timeout_seconds' => (int) env('AI_SUPABASE_TIMEOUT', 30),

    // === Output Language ===

    // Bahasa Indonesia untuk semua output AI (chat answer + enrichment).
    'output_language' => 'id',

];
