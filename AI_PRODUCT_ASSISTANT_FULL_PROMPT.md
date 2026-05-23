# Prompt AI Coding Agent — Build AI Product Assistant System

## Tujuan

Saya ingin membuat sistem AI Product Assistant di aplikasi Laravel saya.

Sistem ini terdiri dari 2 fitur utama:

```text
1. AI Product Knowledge Enrichment
2. AI Product Chat / AI Product Finder
```

## Gambaran Besar

Produk saya sudah ada di Firebase Realtime Database.

Produk biasanya sudah punya data dasar seperti:

```text
nama produk
kategori
harga
kode produk / SKU jika ada
barcode jika ada
brand jika ada
```

Masalahnya, produk belum punya data pengetahuan seperti:

```text
manfaat
fungsi
target hewan
gejala terkait
kategori penggunaan
ukuran / varian / volume / berat
kandungan
aturan pakai
peringatan
sumber informasi
confidence score
```

Saya ingin AI membantu melengkapi data pengetahuan produk tersebut, lalu data yang sudah di-approve bisa dipakai oleh fitur chat agar karyawan bisa mencari produk dengan bahasa natural.

Contoh karyawan bertanya:

```text
obat untuk ayam sakit lemas
vitamin untuk unggas
makanan kucing untuk bulu rontok
obat ikan jamuran
cara pakainya gimana?
yang paling murah yang mana?
ada alternatif lain?
```

AI harus menjawab hanya berdasarkan produk saya sendiri.

AI tidak boleh merekomendasikan produk di luar database.

---

# Existing Tech Stack

Aplikasi existing menggunakan:

## Backend

- Laravel ^12.0
- Laravel Sanctum ^4.0
- Firebase Realtime Database via `kreait/laravel-firebase ^6.2`
- MySQL untuk users/auth dan tabel Laravel tertentu
- Laravel Tinker ^2.10
- PHPSpreadsheet ^5.7 untuk export laporan
- Runtime development: WAMP/Windows, path `S:\wamp64`
- Laravel dijalankan dengan `php artisan serve` port 8000

## Frontend

- Blade server-rendered
- Tailwind CSS ^4.0 via `@tailwindcss/vite`
- Vite ^7.0
- `laravel-vite-plugin ^2.0`
- Axios ^1.11
- Alpine.js boleh dipakai jika sesuai pola existing
- Vanilla JS boleh dipakai
- Module type: ESM
- Tidak pakai React
- Tidak pakai Vue
- Tidak pakai Inertia
- Tidak pakai TypeScript

## Dev Tooling

- Pint ^1.24
- PHPUnit ^11.5
- Mockery ^1.6
- Faker ^1.23
- Pail ^1.2
- Sail ^1.41
- Collision ^8.6
- concurrently ^9.0

## Arsitektur Existing

Aplikasi menggunakan hybrid storage:

```text
Firebase Realtime Database
= source of truth untuk business data

MySQL
= users/auth dan tabel Laravel tertentu
```

Admin/Supervisor page berada di:

```text
/admin/*
```

Jangan mengubah arsitektur besar aplikasi.

Jangan memindahkan semua data produk ke MySQL/PostgreSQL.

Jangan menambahkan React/Vue/Inertia.

Jangan membuat SPA.

Ikuti style existing:

```text
Blade + Tailwind + Axios/Vanilla JS + Laravel Service Classes
```

---

# Teknologi Tambahan yang Dipakai

Tambahkan integrasi:

```text
Gemini API
= generate embedding, AI answer, dan product knowledge extraction

Supabase PostgreSQL + pgvector
= vector search index untuk produk

Optional Web Search Provider
= untuk mencari data produk dari internet saat enrichment
```

Supabase hanya dipakai untuk vector search index, bukan database utama produk.

Firebase tetap menjadi source of truth untuk produk dan product knowledge.

---

# Alur Sistem End-to-End

## 1. Product Enrichment Flow

```text
Produk existing di Firebase
        ↓
Admin klik Generate Knowledge
        ↓
Laravel mengambil data produk
        ↓
Laravel melakukan web research / search provider
        ↓
Gemini mengekstrak informasi produk
        ↓
Hasil disimpan sebagai draft di Firebase product_knowledge_base
        ↓
Admin review/edit
        ↓
Admin approve
        ↓
Knowledge menjadi approved
        ↓
Laravel generate embedding
        ↓
Embedding disimpan ke Supabase pgvector
        ↓
Produk siap dipakai oleh AI Product Chat
```

## 2. AI Product Chat Flow

```text
Karyawan bertanya di halaman chat
        ↓
Laravel generate embedding pertanyaan via Gemini
        ↓
Laravel cari produk mirip di Supabase pgvector
        ↓
Supabase mengembalikan product_id paling relevan
        ↓
Laravel mengambil detail produk terbaru dari Firebase
        ↓
Laravel mengambil product knowledge approved dari Firebase
        ↓
Laravel mengirim hanya top 3-5 produk ke Gemini Flash
        ↓
Gemini menyusun jawaban
        ↓
Jawaban tampil ke karyawan
```

Important:

```text
Jangan kirim semua produk ke Gemini.
Kirim hanya produk relevan hasil vector search.
AI tidak boleh menyebut produk di luar daftar yang dikirim sistem.
```

---

# Firebase Data Structure

## Existing Product Data

Produk existing diasumsikan ada di path:

```text
products/{productId}
```

Jika struktur project berbeda, buat service agar path mudah disesuaikan.

Contoh data produk:

```json
{
  "id": "PRD001",
  "name": "Vita Chick",
  "category": "Vitamin",
  "price": 15000,
  "brand": "Medion",
  "sku": "VC001",
  "barcode": "899xxxx"
}
```

## New Product Knowledge Data

Simpan product knowledge di Firebase path:

```text
product_knowledge_base/{productId}
```

Contoh struktur:

```json
{
  "product_id": "PRD001",
  "product_name": "Vita Chick",
  "brand": "Medion",
  "category": "Vitamin Unggas",
  "manfaat": "Membantu menjaga daya tahan tubuh unggas.",
  "fungsi": [
    "Membantu pemulihan unggas",
    "Mendukung kondisi tubuh ayam saat stres"
  ],
  "target_hewan": [
    "ayam",
    "unggas"
  ],
  "gejala_terkait": [
    "lemas",
    "nafsu makan turun",
    "stres"
  ],
  "kategori_penggunaan": [
    "vitamin",
    "daya tahan tubuh",
    "pemulihan"
  ],
  "ukuran_varian": [
    {
      "label": "10 gram",
      "type": "berat",
      "value": "10",
      "unit": "gram"
    }
  ],
  "kandungan": "Kosongkan jika tidak ditemukan.",
  "aturan_pakai": "Ikuti aturan pakai pada kemasan.",
  "peringatan": "Gunakan sesuai label produk atau arahan ahli.",
  "sumber": [
    {
      "url": "https://example.com/product",
      "title": "Judul sumber",
      "source_type": "official_website",
      "evidence_summary": "Ringkasan pendek informasi yang mendukung data produk."
    }
  ],
  "confidence_score": 0.82,
  "status": "draft",
  "review_note": "Sumber resmi ditemukan, tetap perlu dicek admin.",
  "generated_by": 1,
  "approved_by": null,
  "approved_at": null,
  "created_at": "2026-05-23T10:00:00+07:00",
  "updated_at": "2026-05-23T10:00:00+07:00"
}
```

Status yang valid:

```text
draft
approved
rejected
needs_review
```

Meaning:

```text
draft = hasil AI sudah dibuat, belum dicek admin
approved = sudah dicek dan boleh dipakai AI Product Chat
rejected = ditolak
needs_review = sumber lemah / data belum yakin / perlu dicek manual
```

Only `approved` data boleh dipakai untuk AI Product Chat dan vector search.

---

# Supabase pgvector

Supabase digunakan hanya untuk menyimpan vector produk yang sudah approved.

Buat file SQL dokumentasi:

```text
database/supabase/ai_product_vectors.sql
```

Isi:

```sql
CREATE EXTENSION IF NOT EXISTS vector;

CREATE TABLE IF NOT EXISTS ai_product_vectors (
    id BIGSERIAL PRIMARY KEY,
    product_id TEXT NOT NULL UNIQUE,
    content TEXT NOT NULL,
    embedding vector(768),
    status TEXT DEFAULT 'approved',
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS ai_product_vectors_embedding_hnsw_idx
ON ai_product_vectors
USING hnsw (embedding vector_cosine_ops);

CREATE INDEX IF NOT EXISTS ai_product_vectors_product_id_idx
ON ai_product_vectors (product_id);

CREATE INDEX IF NOT EXISTS ai_product_vectors_status_idx
ON ai_product_vectors (status);
```

Important:

```text
Sesuaikan vector(768) dengan dimensi embedding Gemini yang dipakai.
Jika model embedding menghasilkan dimensi berbeda, update schema.
Jangan buat Laravel migration untuk Supabase kecuali project sudah punya koneksi Postgres khusus.
```

Content untuk embedding harus dibuat dari gabungan:

```text
nama produk
kategori
harga
brand
manfaat
fungsi
target hewan
gejala terkait
kategori penggunaan
ukuran/varian
aturan pakai
peringatan
```

Contoh content:

```text
Nama: Vita Chick.
Brand: Medion.
Kategori: Vitamin Unggas.
Harga: Rp15.000.
Manfaat: Membantu menjaga daya tahan tubuh unggas.
Fungsi: Membantu pemulihan unggas, mendukung kondisi tubuh ayam saat stres.
Target hewan: ayam, unggas.
Gejala terkait: lemas, stres, nafsu makan turun.
Aturan pakai: Ikuti aturan pakai pada kemasan.
Peringatan: Gunakan sesuai label produk.
```

---

# Environment Variables

Tambahkan env:

```env
GEMINI_API_KEY=
GEMINI_EMBEDDING_MODEL=text-embedding-004
GEMINI_CHAT_MODEL=gemini-1.5-flash

SUPABASE_URL=
SUPABASE_SERVICE_ROLE_KEY=
SUPABASE_VECTOR_TABLE=ai_product_vectors

PRODUCT_RESEARCH_PROVIDER=disabled
SERPER_API_KEY=
GOOGLE_CUSTOM_SEARCH_API_KEY=
GOOGLE_CUSTOM_SEARCH_ENGINE_ID=

AI_PRODUCT_CHAT_MAX_PRODUCTS=5
AI_PRODUCT_ENRICHMENT_BATCH_LIMIT=20
```

Important:

```text
SUPABASE_SERVICE_ROLE_KEY hanya boleh digunakan di backend Laravel.
Jangan expose key ke frontend.
```

---

# Config File

Buat config:

```text
config/ai_product_assistant.php
```

Isi minimal:

```php
return [
    'gemini_api_key' => env('GEMINI_API_KEY'),
    'gemini_embedding_model' => env('GEMINI_EMBEDDING_MODEL', 'text-embedding-004'),
    'gemini_chat_model' => env('GEMINI_CHAT_MODEL', 'gemini-1.5-flash'),

    'supabase_url' => env('SUPABASE_URL'),
    'supabase_service_role_key' => env('SUPABASE_SERVICE_ROLE_KEY'),
    'supabase_vector_table' => env('SUPABASE_VECTOR_TABLE', 'ai_product_vectors'),

    'product_research_provider' => env('PRODUCT_RESEARCH_PROVIDER', 'disabled'),
    'serper_api_key' => env('SERPER_API_KEY'),
    'google_custom_search_api_key' => env('GOOGLE_CUSTOM_SEARCH_API_KEY'),
    'google_custom_search_engine_id' => env('GOOGLE_CUSTOM_SEARCH_ENGINE_ID'),

    'chat_max_products' => env('AI_PRODUCT_CHAT_MAX_PRODUCTS', 5),
    'enrichment_batch_limit' => env('AI_PRODUCT_ENRICHMENT_BATCH_LIMIT', 20),
];
```

---

# Routes

Tambahkan route admin dengan middleware auth/admin sesuai pola existing project.

## AI Product Chat Routes

```php
GET  /admin/ai-product-chat
POST /admin/ai-product-chat/ask
POST /admin/ai-product-chat/feedback
```

Suggested names:

```php
admin.ai-product-chat.index
admin.ai-product-chat.ask
admin.ai-product-chat.feedback
```

## AI Product Enrichment Routes

```php
GET  /admin/ai-product-enrichment
GET  /admin/ai-product-enrichment/{productId}
POST /admin/ai-product-enrichment/{productId}/generate
POST /admin/ai-product-enrichment/generate-batch
PUT  /admin/ai-product-enrichment/{productId}
POST /admin/ai-product-enrichment/{productId}/approve
POST /admin/ai-product-enrichment/{productId}/reject
POST /admin/ai-product-enrichment/{productId}/needs-review
POST /admin/ai-product-enrichment/{productId}/sync-vector
```

Suggested names:

```php
admin.ai-product-enrichment.index
admin.ai-product-enrichment.show
admin.ai-product-enrichment.generate
admin.ai-product-enrichment.generate-batch
admin.ai-product-enrichment.update
admin.ai-product-enrichment.approve
admin.ai-product-enrichment.reject
admin.ai-product-enrichment.needs-review
admin.ai-product-enrichment.sync-vector
```

---

# Controllers

Create:

```text
app/Http/Controllers/Admin/AiProductChatController.php
app/Http/Controllers/Admin/AiProductEnrichmentController.php
```

## AiProductChatController

Methods:

```php
index()
ask(Request $request)
feedback(Request $request)
```

## AiProductEnrichmentController

Methods:

```php
index(Request $request)
show(string $productId)
generate(string $productId)
generateBatch(Request $request)
update(Request $request, string $productId)
approve(string $productId)
reject(Request $request, string $productId)
markNeedsReview(Request $request, string $productId)
syncVector(string $productId)
```

Controllers harus tipis. Business logic harus di service.

---

# Service Layer

Buat folder:

```text
app/Services/Ai
```

Buat services:

```text
app/Services/Ai/GeminiService.php
app/Services/Ai/SupabaseVectorService.php
app/Services/Ai/ProductKnowledgeService.php
app/Services/Ai/ProductWebResearchService.php
app/Services/Ai/ProductKnowledgeExtractionService.php
app/Services/Ai/ProductEnrichmentService.php
app/Services/Ai/ProductVectorSyncService.php
app/Services/Ai/AiProductChatService.php
```

---

## 1. GeminiService

Responsibilities:

```text
- Generate embedding dari teks
- Generate jawaban chat AI
- Extract product knowledge dari sumber research
- Handle API errors
```

Methods:

```php
public function generateEmbedding(string $text): array;

public function generateChatAnswer(
    string $question,
    array $products,
    array $chatHistory = [],
    array $options = []
): string;

public function extractProductKnowledge(
    array $product,
    array $researchResults
): array;
```

---

## 2. SupabaseVectorService

Responsibilities:

```text
- Upsert vector produk ke Supabase
- Search similar product vectors
- Delete/deactivate product vector
```

Methods:

```php
public function upsertProductVector(array $payload): bool;

public function searchSimilarProducts(array $embedding, int $limit = 10): array;

public function deleteProductVector(string $productId): bool;
```

Payload upsert:

```php
[
    'product_id' => 'PRD001',
    'content' => '...',
    'embedding' => [...],
    'status' => 'approved',
]
```

---

## 3. ProductKnowledgeService

Responsibilities:

```text
- Ambil produk dari Firebase
- Ambil knowledge dari Firebase
- Simpan/update knowledge ke Firebase
- Build embedding content
- Build product context untuk AI Chat
```

Methods:

```php
public function getProduct(string $productId): ?array;

public function listProducts(array $filters = []): array;

public function getKnowledge(string $productId): ?array;

public function saveKnowledge(string $productId, array $knowledge): bool;

public function updateKnowledgeStatus(
    string $productId,
    string $status,
    ?int $userId = null,
    ?string $note = null
): bool;

public function buildEmbeddingContent(array $product, ?array $knowledge): string;

public function buildProductContext(array $product, ?array $knowledge): array;
```

---

## 4. ProductWebResearchService

Responsibilities:

```text
- Cari informasi produk dari internet/provider yang dikonfigurasi
- Return daftar sumber/snippet
- Provider harus bisa diganti
```

Methods:

```php
public function searchProduct(array $product): array;
```

Return format:

```php
[
    [
        'title' => '...',
        'url' => 'https://...',
        'snippet' => '...',
        'source_type' => 'official_website|distributor|marketplace|blog|unknown',
    ]
]
```

Provider behavior:

```text
Jika PRODUCT_RESEARCH_PROVIDER=disabled:
return error/message bahwa provider belum dikonfigurasi.

Jika PRODUCT_RESEARCH_PROVIDER=serper:
gunakan SERPER_API_KEY.

Jika PRODUCT_RESEARCH_PROVIDER=google_custom_search:
gunakan GOOGLE_CUSTOM_SEARCH_API_KEY dan GOOGLE_CUSTOM_SEARCH_ENGINE_ID.
```

Buat struktur service fleksibel agar provider lain bisa ditambahkan.

---

## 5. ProductKnowledgeExtractionService

Responsibilities:

```text
- Menerima product + research results
- Meminta Gemini mengekstrak data structured JSON
- Validasi output
- Tentukan status awal draft/needs_review
- Tentukan confidence_score
```

Methods:

```php
public function extract(array $product, array $researchResults): array;
```

Output harus mengikuti struktur product_knowledge_base.

Jika data tidak ditemukan, jangan mengarang.

---

## 6. ProductEnrichmentService

Responsibilities:

```text
- Orchestrate enrichment satu produk
- Ambil product dari Firebase
- Web research
- Extract knowledge
- Save draft ke Firebase
- Save job/log ke MySQL
```

Methods:

```php
public function generateForProduct(string $productId, ?int $userId = null): array;

public function generateBatch(int $limit = 20, ?int $userId = null): array;
```

Batch jangan terlalu besar. Default 20.

---

## 7. ProductVectorSyncService

Responsibilities:

```text
- Sync approved product knowledge ke Supabase pgvector
- Generate embedding content
- Generate embedding
- Upsert vector
```

Methods:

```php
public function syncProduct(string $productId): array;

public function syncBatch(int $limit = 50): array;
```

Only sync if knowledge status is `approved`.

---

## 8. AiProductChatService

Responsibilities:

```text
- Handle pertanyaan karyawan
- Detect follow-up
- Generate embedding pertanyaan
- Search product vectors
- Fetch product details from Firebase
- Re-rank products
- Send top products to Gemini
- Save chat session/message
```

Methods:

```php
public function ask(?int $sessionId, int $userId, string $question): array;
```

Return:

```php
[
    'success' => true,
    'session_id' => 1,
    'answer' => '...',
    'products' => [],
    'metadata' => [
        'source' => 'vector_search',
        'product_ids' => [],
    ],
]
```

---

# MySQL Migrations and Models

Create migrations and models for:

```text
ai_chat_sessions
ai_chat_messages
ai_product_feedbacks
ai_product_enrichment_jobs
ai_product_enrichment_logs
```

## Table: ai_chat_sessions

Fields:

```text
id
user_id nullable index
title nullable
last_product_ids json nullable
primary_product_id nullable string
created_at
updated_at
```

## Table: ai_chat_messages

Fields:

```text
id
session_id foreign
role string
message longText
metadata json nullable
created_at
updated_at
```

Role values:

```text
user
assistant
system
```

## Table: ai_product_feedbacks

Fields:

```text
id
session_id nullable
message_id nullable
user_id nullable
question text nullable
answer longText nullable
product_ids json nullable
rating nullable string
reason nullable string
note text nullable
created_at
updated_at
```

Rating values:

```text
helpful
not_helpful
```

## Table: ai_product_enrichment_jobs

Fields:

```text
id
product_id string index
product_name nullable string
status string index
source_count integer default 0
confidence_score decimal nullable
generated_by nullable
approved_by nullable
approved_at nullable timestamp
error_message text nullable
metadata json nullable
created_at
updated_at
```

Status values:

```text
pending
processing
draft
approved
rejected
needs_review
failed
```

## Table: ai_product_enrichment_logs

Fields:

```text
id
job_id foreign nullable
product_id string index
action string
message text nullable
metadata json nullable
user_id nullable
created_at
updated_at
```

Models:

```text
app/Models/AiChatSession.php
app/Models/AiChatMessage.php
app/Models/AiProductFeedback.php
app/Models/AiProductEnrichmentJob.php
app/Models/AiProductEnrichmentLog.php
```

---

# AI Product Enrichment Prompt Rules

Gemini extraction harus mengikuti aturan:

```text
Anda adalah asisten ekstraksi data produk.

Tugas:
Ekstrak pengetahuan produk dari sumber yang diberikan.

Aturan:
1. Jangan mengarang data.
2. Jika informasi tidak ditemukan, isi null, string kosong, atau array kosong.
3. Jangan membuat klaim medis pasti.
4. Jangan menulis "menyembuhkan" kecuali sumber resmi menyatakan demikian.
5. Prioritaskan sumber resmi produsen, distributor resmi, katalog resmi, dan official store.
6. Blog/artikel umum hanya sumber pendukung lemah.
7. Simpan URL sumber.
8. Berikan confidence_score 0 sampai 1.
9. Jika sumber resmi tidak ditemukan, status harus needs_review.
10. Jika nama produk tidak cocok jelas dengan sumber, status harus needs_review.
11. Output harus JSON valid sesuai schema.
```

Expected JSON:

```json
{
  "product_id": "",
  "product_name": "",
  "brand": "",
  "category": "",
  "manfaat": "",
  "fungsi": [],
  "target_hewan": [],
  "gejala_terkait": [],
  "kategori_penggunaan": [],
  "ukuran_varian": [],
  "kandungan": "",
  "aturan_pakai": "",
  "peringatan": "",
  "sumber": [],
  "confidence_score": 0,
  "status": "needs_review",
  "review_note": ""
}
```

---

# AI Product Chat Prompt Rules

Gemini chat harus mengikuti aturan:

```text
Anda adalah asisten pencarian produk internal untuk toko/petshop.

Aturan wajib:
1. Jawab hanya berdasarkan daftar produk yang diberikan sistem.
2. Jangan menyebut produk yang tidak ada di daftar.
3. Jangan membuat diagnosis medis pasti.
4. Jangan mengklaim produk menyembuhkan penyakit kecuali data produk menyatakan demikian.
5. Gunakan bahasa aman seperti "kemungkinan cocok", "bisa ditawarkan", "relevan untuk gejala".
6. Jika gejala kurang jelas, sarankan karyawan menanyakan gejala tambahan ke customer.
7. Jika tidak ada produk cocok, katakan produk belum ditemukan di database.
8. Prioritaskan jawaban yang praktis untuk karyawan baru.
9. Tampilkan nama produk, kategori, harga, alasan singkat, dan cara menawarkan.
10. Jika ada aturan pakai, tampilkan secara ringkas.
11. Jika aturan pakai tidak ada, tulis "ikuti aturan pakai pada kemasan".
12. Jangan memberi saran dosis yang tidak ada di data produk.
13. Jangan menyarankan produk di luar database.
```

Format jawaban:

```text
Saya menemukan beberapa produk yang relevan:

1. Nama Produk
Kategori:
Harga:
Alasan cocok:
Cara menawarkan ke customer:
Aturan pakai:

Pertanyaan lanjutan yang bisa ditanyakan:
- ...
```

---

# Product Ranking for Chat

Setelah Supabase mengembalikan product_id, Laravel harus ranking ulang.

Suggested scoring:

```text
+40 jika product knowledge status approved
+30 jika target_hewan cocok dengan pertanyaan
+25 jika gejala_terkait cocok dengan pertanyaan
+20 jika kategori cocok
+10 jika confidence_score >= 0.8
-50 jika knowledge tidak lengkap
```

Return top 3-5 products to Gemini.

Do not send more than 5 products to Gemini by default.

---

# Chat Session Behavior

Simpan chat agar follow-up bisa dipahami.

Saat pertanyaan pertama:

```text
obat untuk ayam sakit lemas
```

Simpan recommended product ids ke:

```text
ai_chat_sessions.last_product_ids
```

Simpan produk teratas ke:

```text
ai_chat_sessions.primary_product_id
```

Saat pertanyaan lanjutan:

```text
cara pakainya gimana?
yang paling murah?
ada alternatif lain?
```

Gunakan:

```text
last_product_ids
primary_product_id
beberapa pesan terakhir
```

Jangan kirim seluruh riwayat chat panjang ke Gemini.

---

# UI Requirements

## 1. AI Product Chat Page

Create Blade:

```text
resources/views/admin/ai-product-chat/index.blade.php
```

UI:

```text
- Header: AI Product Chat
- Input pertanyaan
- Tombol kirim
- Loading state
- Chat message area
- List produk rekomendasi
- Feedback helpful/not helpful
```

Use:

```text
Blade + Tailwind + Axios
```

No React/Vue/Inertia.

## 2. AI Product Enrichment Page

Create Blade:

```text
resources/views/admin/ai-product-enrichment/index.blade.php
resources/views/admin/ai-product-enrichment/show.blade.php
```

Index UI:

```text
- Header: AI Product Enrichment
- Search produk
- Filter status: all, no_knowledge, draft, approved, rejected, needs_review
- Table/list produk
- Status knowledge
- Confidence score
- Actions:
  - Generate
  - View
  - Approve
  - Reject
  - Needs Review
  - Sync Vector
```

Show UI:

```text
- Detail produk
- Knowledge hasil AI
- Editable fields:
  - manfaat
  - fungsi
  - target_hewan
  - gejala_terkait
  - kategori_penggunaan
  - ukuran_varian
  - kandungan
  - aturan_pakai
  - peringatan
  - sumber
  - confidence_score
  - review_note
- Buttons:
  - Save
  - Approve
  - Reject
  - Mark Needs Review
  - Sync Vector
```

---

# Validation

## Chat Ask Request

```php
'session_id' => ['nullable', 'integer', 'exists:ai_chat_sessions,id'],
'question' => ['required', 'string', 'min:2', 'max:500'],
```

## Feedback Request

```php
'session_id' => ['nullable', 'integer'],
'message_id' => ['nullable', 'integer'],
'rating' => ['required', 'string', 'in:helpful,not_helpful'],
'reason' => ['nullable', 'string', 'max:255'],
'note' => ['nullable', 'string', 'max:1000'],
```

## Knowledge Update Request

Validate:

```php
'manfaat' => ['nullable', 'string'],
'fungsi' => ['nullable', 'array'],
'target_hewan' => ['nullable', 'array'],
'gejala_terkait' => ['nullable', 'array'],
'kategori_penggunaan' => ['nullable', 'array'],
'ukuran_varian' => ['nullable', 'array'],
'kandungan' => ['nullable', 'string'],
'aturan_pakai' => ['nullable', 'string'],
'peringatan' => ['nullable', 'string'],
'sumber' => ['nullable', 'array'],
'confidence_score' => ['nullable', 'numeric', 'min:0', 'max:1'],
'review_note' => ['nullable', 'string'],
```

---

# Error Handling

Handle safely:

```text
Gemini API gagal
Supabase gagal
Firebase product tidak ditemukan
Web research provider belum dikonfigurasi
Embedding kosong
Tidak ada produk relevan
Session tidak ditemukan
Unauthorized user
JSON extraction invalid
```

Frontend harus menampilkan pesan ramah:

```text
AI sedang tidak bisa dihubungi. Coba lagi beberapa saat.
```

atau:

```text
Produk yang sesuai belum ditemukan di database.
```

atau:

```text
Web research provider belum dikonfigurasi.
```

Jangan expose stack trace ke frontend.

---

# Security

```text
- Semua endpoint harus protected by auth/admin middleware.
- Validasi semua request.
- Jangan expose GEMINI_API_KEY.
- Jangan expose SUPABASE_SERVICE_ROLE_KEY.
- Jangan expose provider search API key.
- Jangan simpan prompt rahasia di frontend.
- Escape output HTML.
- Jangan inject raw AI response sebagai HTML tanpa sanitization.
- Rate limit endpoint chat jika memungkinkan.
```

---

# Performance Rules

```text
- Jangan kirim semua produk ke Gemini.
- Product enrichment batch default max 20.
- Vector sync batch default max 50.
- Product chat kirim max 5 produk ke Gemini.
- Gunakan Supabase vector search untuk pencarian semantic.
- Firebase tetap source of truth.
- Cache optional, jangan wajibkan Redis.
- Jangan require queue worker untuk MVP.
```

Jika perlu batch besar, buat artisan command optional.

---

# Artisan Commands Optional

Buat command jika mudah:

```bash
php artisan ai:product-enrichment:generate --limit=20
php artisan ai:product-vectors:sync --limit=50
```

Commands harus aman dijalankan di WAMP/Windows.

---

# Testing

Tambahkan basic tests:

```text
tests/Unit/Services/ProductKnowledgeServiceTest.php
tests/Unit/Services/ProductEnrichmentServiceTest.php
tests/Unit/Services/AiProductChatServiceTest.php
```

Test minimal:

```text
1. ProductKnowledgeService builds embedding content correctly.
2. ProductEnrichmentService stores draft knowledge.
3. Enrichment does not approve automatically.
4. AiProductChatService handles no products found.
5. AiProductChatService limits products sent to Gemini to max 5.
6. Follow-up question uses session primary_product_id.
7. Feedback can be stored.
```

Mock GeminiService, SupabaseVectorService, ProductWebResearchService, and Firebase calls.

---

# Coding Style

Ikuti style Laravel existing.

Rules:

```text
- Controller tipis
- Business logic di service
- Blade untuk UI
- Axios untuk request frontend
- Tailwind untuk styling
- Jangan React/Vue/Inertia
- Jangan TypeScript
- Jangan ubah arsitektur storage utama
- Firebase tetap source of truth
- MySQL hanya untuk chat/session/log/feedback
- Supabase hanya vector index
```

---

# Expected Deliverables

Implement:

```text
1. Routes
2. Controllers
3. Services
4. MySQL migrations
5. Eloquent models
6. Blade pages
7. Axios frontend logic
8. Supabase SQL documentation file
9. Config file
10. Optional artisan commands
11. Basic tests
12. Clear comments for external provider setup
```

---

# Definition of Done

Fitur dianggap selesai jika:

```text
1. Admin bisa membuka /admin/ai-product-enrichment.
2. Admin bisa melihat list produk dari Firebase.
3. Admin bisa generate knowledge produk.
4. Hasil AI tersimpan sebagai draft, bukan langsung approved.
5. Admin bisa edit, approve, reject, needs_review.
6. Approved knowledge bisa di-sync ke Supabase pgvector.
7. Admin/karyawan bisa membuka /admin/ai-product-chat.
8. Karyawan bisa bertanya dengan bahasa natural.
9. Sistem mencari produk lewat Supabase vector search.
10. Sistem mengambil detail produk dari Firebase.
11. Gemini menjawab hanya berdasarkan produk yang ditemukan.
12. Follow-up question seperti "cara pakainya gimana?" bisa memakai konteks session.
13. Feedback helpful/not_helpful bisa disimpan.
14. Tidak ada React/Vue/Inertia.
15. Tidak ada migrasi besar dari Firebase ke database lain.
16. API keys tidak terekspos ke frontend.
