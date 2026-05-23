<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * ProductKnowledgeExtractionService
 *
 * Bertanggung jawab atas:
 *   1. Klasifikasi tipe produk berdasarkan kategori (medical/food/equipment/livestock/pest_control/general).
 *   2. Membangun prompt grounded search yang ADAPTIF per tipe.
 *   3. Memanggil GeminiService::groundedExtract().
 *   4. Mem-parse JSON yang dikembalikan Gemini menjadi struktur knowledge.
 *   5. Mengklasifikasikan sumber (official_website / marketplace / blog / unknown).
 *
 * Output structure (semua field opsional, isi sesuai tipe produk):
 * {
 *   "tipe_produk": "medical|food|equipment|livestock|pest_control|general",
 *   "brand": string|null,
 *   "manfaat": string,                  // semua tipe
 *   "fungsi": array,                    // semua tipe
 *   "target_hewan": array,              // semua tipe (untuk pancing: ikan target; kandang: hewan pemilik)
 *   "gejala_terkait": array,            // medical only
 *   "kategori_penggunaan": array,       // semua tipe
 *   "aturan_pakai": string,             // medical/food/pest
 *   "peringatan": string,               // medical/pest/equipment listrik
 *   "ukuran_varian": array,             // semua tipe (kemasan/ukuran)
 *   "spesifikasi": object,              // equipment - key/value bebas (panjang, bahan, kapasitas, dll)
 *   "sources": array,                   // [{title, url, source_type}]
 *   "confidence_score": float,
 *   "raw_text": string,
 *   "search_queries": array
 * }
 */
class ProductKnowledgeExtractionService
{
    public const TYPE_MEDICAL = 'medical';
    public const TYPE_FOOD = 'food';
    public const TYPE_EQUIPMENT = 'equipment';
    public const TYPE_LIVESTOCK = 'livestock';
    public const TYPE_PEST_CONTROL = 'pest_control';
    public const TYPE_GENERAL = 'general';

    protected GeminiService $gemini;

    public function __construct(GeminiService $gemini)
    {
        $this->gemini = $gemini;
    }

    /**
     * Ekstrak knowledge dari nama produk (+ optional category name).
     *
     * @return array|null Knowledge struct, atau null jika gagal.
     */
    public function extractForProduct(string $productName, ?string $categoryName = null): ?array
    {
        if (trim($productName) === '') {
            return null;
        }

        $tipe = $this->classifyProductType($categoryName);
        $prompt = $this->buildPrompt($productName, $categoryName, $tipe);

        $result = $this->gemini->groundedExtract($prompt, 0.2);
        if (! $result || empty($result['text'])) {
            Log::warning('Knowledge extraction: empty grounded result', ['product' => $productName]);

            return null;
        }

        $parsed = $this->parseJsonFromText((string) $result['text']);
        if (! $parsed) {
            Log::warning('Knowledge extraction: JSON parse failed', [
                'product' => $productName,
                'raw_excerpt' => substr((string) $result['text'], 0, 300),
            ]);

            return null;
        }

        $sources = $this->classifySources($result['grounding_chunks'] ?? []);

        return [
            'tipe_produk' => $tipe,  // Hardcode dari local classify - lebih reliable daripada output Gemini
            'brand' => $this->stringOrNull($parsed['brand'] ?? null),
            'manfaat' => $this->stringOrEmpty($parsed['manfaat'] ?? ''),
            'fungsi' => $this->arrayOfStrings($parsed['fungsi'] ?? []),
            'target_hewan' => $this->arrayOfStrings($parsed['target_hewan'] ?? []),
            'gejala_terkait' => $this->arrayOfStrings($parsed['gejala_terkait'] ?? []),
            'kategori_penggunaan' => $this->arrayOfStrings($parsed['kategori_penggunaan'] ?? []),
            'aturan_pakai' => $this->stringOrEmpty($parsed['aturan_pakai'] ?? ''),
            'peringatan' => $this->stringOrEmpty($parsed['peringatan'] ?? ''),
            'ukuran_varian' => $this->arrayOfStrings($parsed['ukuran_varian'] ?? []),
            'spesifikasi' => $this->keyValueObject($parsed['spesifikasi'] ?? null),
            'sources' => $sources,
            'confidence_score' => $this->confidence($parsed['confidence_score'] ?? null, $sources),
            'raw_text' => (string) $result['text'],
            'search_queries' => is_array($result['web_search_queries'] ?? null) ? $result['web_search_queries'] : [],
        ];
    }

    /**
     * Klasifikasi tipe produk berdasarkan nama kategori.
     */
    public function classifyProductType(?string $categoryName): string
    {
        $cat = mb_strtolower(trim((string) $categoryName));
        if ($cat === '') {
            return self::TYPE_GENERAL;
        }

        // ESSENCE = umpan pancing aroma → food (cek dulu sebelum kena 'essence' di medical)
        if (str_contains($cat, 'essence')) {
            return self::TYPE_FOOD;
        }

        // Medical: obat, vitamin, perawatan medis, nectar (suplemen burung)
        $medicalKeywords = ['obat', 'vitamin', 'nectar', 'perawatan'];
        foreach ($medicalKeywords as $kw) {
            if (str_contains($cat, $kw)) {
                return self::TYPE_MEDICAL;
            }
        }

        // Food: pakan, umpan
        if (str_contains($cat, 'pakan') || str_contains($cat, 'umpan')) {
            return self::TYPE_FOOD;
        }

        // Equipment: joran, reel, kail, senar, sparepart, tegek, aksesoris, kandang, pasir
        $equipmentKeywords = ['joran', 'reel', 'kail', 'senar', 'sparepart', 'tegek', 'aksesoris', 'kandang', 'pasir'];
        foreach ($equipmentKeywords as $kw) {
            if (str_contains($cat, $kw)) {
                return self::TYPE_EQUIPMENT;
            }
        }

        // Livestock: hewan hidup
        if ($cat === 'hewan' || str_contains($cat, 'hewan ')) {
            return self::TYPE_LIVESTOCK;
        }

        // Pest control: racun, perangkap
        if (str_contains($cat, 'racun') || str_contains($cat, 'perangkap')) {
            return self::TYPE_PEST_CONTROL;
        }

        return self::TYPE_GENERAL;
    }

    /**
     * Build prompt untuk grounded extraction, ADAPTIF per tipe produk.
     */
    public function buildPrompt(string $productName, ?string $categoryName = null, ?string $tipe = null): string
    {
        $tipe = $tipe ?? $this->classifyProductType($categoryName);
        $cat = $categoryName ? " (kategori: {$categoryName})" : '';

        // Header umum
        $header = <<<HEADER
Anda adalah asisten riset produk untuk toko serbaguna di Indonesia (peternakan, perikanan, petshop, perlengkapan pancing).

PRODUK: {$productName}{$cat}

TUGAS: Cari informasi terpercaya tentang produk ini. Prioritaskan SUMBER RESMI (website pabrikan, toko official store, blog terpercaya).

LARANGAN UMUM:
- JANGAN mengarang fakta. Jika informasi tidak ditemukan, isi field dengan string kosong / array kosong.
- JANGAN sertakan harga (harga dikelola sistem POS terpisah).
- Bahasa: Indonesia, ringkas dan jelas.

OUTPUT: HANYA JSON murni (tanpa markdown fence, tanpa komentar, tanpa teks pengantar).

HEADER;

        // Skema per tipe produk
        $schema = match ($tipe) {
            self::TYPE_MEDICAL => $this->schemaMedical(),
            self::TYPE_FOOD => $this->schemaFood(),
            self::TYPE_EQUIPMENT => $this->schemaEquipment(),
            self::TYPE_LIVESTOCK => $this->schemaLivestock(),
            self::TYPE_PEST_CONTROL => $this->schemaPestControl(),
            default => $this->schemaGeneral(),
        };

        $confidence = <<<CONF

confidence_score:
- 0.9 jika data lengkap dari minimal 1 website resmi pabrikan/distributor
- 0.7 jika data dari marketplace/blog terpercaya
- 0.5 jika hanya potongan informasi
- 0.3 jika tidak yakin
- 0.0 jika produk tidak ditemukan sama sekali

Pastikan JSON valid. Bahasa Indonesia.
CONF;

        return $header.$schema.$confidence;
    }

    protected function schemaMedical(): string
    {
        return <<<S
KONTEKS: Produk ini adalah obat / vitamin / perawatan medis untuk hewan.

LARANGAN KHUSUS:
- JANGAN memberi diagnosis medis pasti. Hanya deskripsikan fungsi/manfaat sesuai data sumber.
- JANGAN klaim "menyembuhkan" kecuali memang ditulis di label/sumber resmi.
- JANGAN beri dosis di luar yang ditulis sumber.

STRUKTUR JSON:
{
  "tipe_produk": "medical",
  "brand": "string atau null",
  "manfaat": "ringkasan manfaat dalam 1-3 kalimat",
  "fungsi": ["fungsi spesifik 1", "fungsi spesifik 2"],
  "target_hewan": ["ayam", "kucing", "burung", ...],
  "gejala_terkait": ["lemas", "tidak nafsu makan", "bulu rontok", ...],
  "kategori_penggunaan": ["vitamin", "antibiotik", "vaksin", "antiparasit", "shampo", "grooming", ...],
  "aturan_pakai": "instruksi dosis/penggunaan dari sumber",
  "peringatan": "peringatan keamanan dari sumber",
  "ukuran_varian": ["Sachet 5g", "Botol 100ml"],
  "spesifikasi": {},
  "confidence_score": 0.0
}

S;
    }

    protected function schemaFood(): string
    {
        return <<<S
KONTEKS: Produk ini adalah pakan / umpan / essence untuk hewan.

PERHATIKAN:
- Untuk PAKAN: cari tahu fase hewan target (anak/dewasa/petelur), kandungan gizi utama.
- Untuk UMPAN/ESSENCE PANCING: cari tahu jenis ikan target, cara penggunaan.

STRUKTUR JSON:
{
  "tipe_produk": "food",
  "brand": "string atau null",
  "manfaat": "ringkasan manfaat 1-3 kalimat",
  "fungsi": ["fase pertumbuhan / kandungan utama / efek pemakaian"],
  "target_hewan": ["ayam pedaging", "ayam petelur", "kucing dewasa", "ikan koi", "ikan mas", "lele", ...],
  "gejala_terkait": [],
  "kategori_penggunaan": ["pakan starter", "pakan grower", "pakan finisher", "umpan ikan air tawar", "essen aroma", ...],
  "aturan_pakai": "cara pemberian (frekuensi, takaran, fase), kosong jika tidak ada",
  "peringatan": "",
  "ukuran_varian": ["50kg", "5kg eceran"],
  "spesifikasi": {
    "kandungan_protein": "20%",
    "kandungan_lemak": "5%",
    "fase": "starter 1-21 hari"
  },
  "confidence_score": 0.0
}

S;
    }

    protected function schemaEquipment(): string
    {
        return <<<S
KONTEKS: Produk ini adalah PERALATAN/AKSESORIS — bisa alat pancing (joran/reel/kail/senar/tegek/sparepart),
aksesoris kandang/hewan, kandang, pasir kucing, atau perlengkapan lain.

PERHATIKAN:
- Untuk ALAT PANCING (joran/reel/kail/senar/tegek): cari spesifikasi teknis (panjang, action, line weight, lure weight, ratio gear, ball bearing, material), jenis ikan target, gaya memancing (laut/tawar/casting/jigging).
- Untuk AKSESORIS KANDANG/HEWAN: cari ukuran, bahan, untuk hewan apa, fungsi spesifik.
- Untuk PASIR KUCING: cari bahan (bentonite/silica/wood pellet), wangi, tipe gumpal/non-gumpal.

LARANGAN:
- "gejala_terkait" KOSONG ([]). Field ini tidak relevan untuk peralatan.
- "aturan_pakai" boleh diisi cara pemakaian / perawatan, tapi BUKAN dosis.

STRUKTUR JSON:
{
  "tipe_produk": "equipment",
  "brand": "string atau null",
  "manfaat": "ringkasan kegunaan dalam 1-3 kalimat",
  "fungsi": ["kegunaan spesifik 1", "kegunaan spesifik 2"],
  "target_hewan": ["ikan air tawar", "ikan laut", "kucing", "burung", "hamster", ...],
  "gejala_terkait": [],
  "kategori_penggunaan": ["joran tegek", "reel spinning", "umpan tiruan", "kandang sangkar", "kandang battery", "pasir gumpal", ...],
  "aturan_pakai": "cara pemakaian / perawatan singkat, kosong jika tidak relevan",
  "peringatan": "kosong, kecuali untuk peralatan listrik / berbahan kimia",
  "ukuran_varian": ["180cm", "210cm", "ukuran L"],
  "spesifikasi": {
    "panjang": "180cm",
    "material": "fiberglass",
    "action": "fast",
    "line_weight": "8-15 lbs",
    "ball_bearing": "5+1",
    "gear_ratio": "5.2:1",
    "kapasitas_pasir": "10 liter"
  },
  "confidence_score": 0.0
}

CATATAN: "spesifikasi" adalah objek key-value BEBAS. Isi field yang relevan dengan jenis produk. Kosongkan {} jika tidak ada data.

S;
    }

    protected function schemaLivestock(): string
    {
        return <<<S
KONTEKS: Produk ini adalah HEWAN HIDUP yang dijual (ternak, unggas, dll).

PERHATIKAN:
- Cari karakteristik bibit/jenis: ras, asal, tujuan budidaya (potong/petelur/aduan/hias).
- Hindari klaim performa pasti (kecepatan tumbuh, jumlah telur) tanpa sumber.

STRUKTUR JSON:
{
  "tipe_produk": "livestock",
  "brand": null,
  "manfaat": "deskripsi singkat hewan dan kegunaan budidaya",
  "fungsi": ["pedaging", "petelur", "aduan", "hias"],
  "target_hewan": [],
  "gejala_terkait": [],
  "kategori_penggunaan": ["ternak komersial", "hobi", "lomba"],
  "aturan_pakai": "tips perawatan dasar jika ada di sumber",
  "peringatan": "",
  "ukuran_varian": ["DOC", "starter", "siap potong", "umur 2 bulan"],
  "spesifikasi": {
    "ras": "Bangkok",
    "asal": "Thailand",
    "berat_dewasa": "2-3 kg"
  },
  "confidence_score": 0.0
}

S;
    }

    protected function schemaPestControl(): string
    {
        return <<<S
KONTEKS: Produk ini adalah RACUN / PERANGKAP HAMA (tikus, semut, dll).

PERHATIKAN:
- Cari bahan aktif, target hama, cara penggunaan.
- WAJIB sertakan peringatan keamanan untuk hewan peliharaan dan anak.

STRUKTUR JSON:
{
  "tipe_produk": "pest_control",
  "brand": "string atau null",
  "manfaat": "ringkasan kegunaan",
  "fungsi": ["fungsi spesifik"],
  "target_hewan": [],
  "gejala_terkait": [],
  "kategori_penggunaan": ["racun tikus", "perangkap lem", "racun semut"],
  "aturan_pakai": "cara penggunaan (penempatan, dosis)",
  "peringatan": "WAJIB diisi - peringatan keamanan",
  "ukuran_varian": ["sachet 25g"],
  "spesifikasi": {
    "bahan_aktif": "Bromadiolone",
    "target_hama": "tikus rumah, tikus got"
  },
  "confidence_score": 0.0
}

S;
    }

    protected function schemaGeneral(): string
    {
        return <<<S
KONTEKS: Tipe produk tidak dapat ditentukan otomatis dari kategori.

STRUKTUR JSON:
{
  "tipe_produk": "general",
  "brand": "string atau null",
  "manfaat": "ringkasan manfaat/fungsi 1-3 kalimat",
  "fungsi": ["fungsi 1", "fungsi 2"],
  "target_hewan": [],
  "gejala_terkait": [],
  "kategori_penggunaan": [],
  "aturan_pakai": "",
  "peringatan": "",
  "ukuran_varian": [],
  "spesifikasi": {},
  "confidence_score": 0.0
}

S;
    }

    /**
     * Parse JSON dari text response (handle markdown fence dan teks pengantar).
     */
    public function parseJsonFromText(string $text): ?array
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }

        // Strip markdown fence ```json ... ```
        if (preg_match('/```(?:json)?\s*(.+?)\s*```/s', $text, $m)) {
            $text = $m[1];
        }

        // Cari kurung kurawal pertama dan terakhir.
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $candidate = substr($text, $start, $end - $start + 1);
        $decoded = json_decode($candidate, true);
        if (! is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    /**
     * Klasifikasi groundingChunks Gemini menjadi sources struct.
     *
     * @param  array  $chunks  Raw groundingChunks dari Gemini.
     * @return array<int, array{title: string, url: string, source_type: string}>
     */
    public function classifySources(array $chunks): array
    {
        $sources = [];
        foreach ($chunks as $chunk) {
            $web = $chunk['web'] ?? null;
            if (! is_array($web)) {
                continue;
            }
            $title = (string) ($web['title'] ?? '');
            $url = (string) ($web['uri'] ?? '');
            if ($title === '' && $url === '') {
                continue;
            }

            $sources[] = [
                'title' => $title,
                'url' => $url,
                'source_type' => $this->guessSourceType($title, $url),
            ];
        }

        // Dedupe by title+url.
        $seen = [];
        $unique = [];
        foreach ($sources as $s) {
            $key = strtolower($s['title'].'|'.$s['url']);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $s;
        }

        return $unique;
    }

    protected function guessSourceType(string $title, string $url): string
    {
        $haystack = strtolower($title.' '.$url);

        $marketplaces = ['tokopedia', 'shopee', 'bukalapak', 'lazada', 'blibli', 'tiktok', 'instagram', 'facebook'];
        foreach ($marketplaces as $kw) {
            if (str_contains($haystack, $kw)) {
                return 'marketplace';
            }
        }

        $blogs = ['blogspot', 'wordpress', 'medium.com', 'kompasiana'];
        foreach ($blogs as $kw) {
            if (str_contains($haystack, $kw)) {
                return 'blog';
            }
        }

        // Heuristik: title dengan domain perusahaan / instansi.
        $official = ['medion', 'sanbe', 'romindo', 'soja', 'pt ', 'pt.', 'official', '.go.id', '.ac.id', 'gov.id', 'farma', 'shimano', 'daiwa', 'pioneer'];
        foreach ($official as $kw) {
            if (str_contains($haystack, $kw)) {
                return 'official_website';
            }
        }

        return 'unknown';
    }

    protected function stringOrNull($v): ?string
    {
        if ($v === null) {
            return null;
        }
        $s = trim((string) $v);

        return $s === '' ? null : $s;
    }

    protected function stringOrEmpty($v): string
    {
        return trim((string) $v);
    }

    protected function arrayOfStrings($v): array
    {
        if (! is_array($v)) {
            return [];
        }
        $out = [];
        foreach ($v as $item) {
            $s = trim((string) $item);
            if ($s !== '') {
                $out[] = $s;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * Parse spesifikasi (key-value bebas). Hanya ambil pasangan string→string/scalar.
     */
    protected function keyValueObject($v): array
    {
        if (! is_array($v)) {
            return [];
        }
        $out = [];
        foreach ($v as $key => $value) {
            $key = trim((string) $key);
            if ($key === '') {
                continue;
            }
            if (is_array($value)) {
                $value = implode(', ', array_map(fn ($x) => trim((string) $x), $value));
            } else {
                $value = trim((string) $value);
            }
            if ($value === '') {
                continue;
            }
            // Normalize key (snake_case-ish, hapus spasi berlebih).
            $cleanKey = preg_replace('/\s+/', '_', mb_strtolower($key));
            $cleanKey = preg_replace('/[^a-z0-9_]/u', '', $cleanKey);
            if ($cleanKey === '') {
                continue;
            }
            $out[$cleanKey] = $value;
        }

        return $out;
    }

    /**
     * Cap confidence_score: max 0.7 jika tidak ada official_website source.
     */
    protected function confidence($raw, array $sources): float
    {
        $score = is_numeric($raw) ? (float) $raw : 0.0;
        $score = max(0.0, min(1.0, $score));

        $hasOfficial = false;
        foreach ($sources as $s) {
            if (($s['source_type'] ?? '') === 'official_website') {
                $hasOfficial = true;
                break;
            }
        }
        if (! $hasOfficial && $score > 0.7) {
            $score = 0.7;
        }
        if (count($sources) === 0 && $score > 0.3) {
            $score = 0.3;
        }

        return round($score, 3);
    }
}
