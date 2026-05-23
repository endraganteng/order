<?php

namespace App\Console\Commands;

use App\Models\AiProductEnrichmentBatch;
use App\Services\FirebaseService;
use App\Services\GeminiService;
use App\Services\SupabaseVectorService;
use Illuminate\Console\Command;

/**
 * AiProductDiagnose
 *
 * Diagnostic tool untuk debugging fitur batch enrichment di production.
 * Cek satu per satu komponen yang dibutuhkan: env, kredensial, koneksi,
 * permission, kemampuan spawn process. Semua hasil ditampilkan dengan
 * status pass/fail/warn supaya user bisa langsung tau mana yang error.
 *
 * Usage:
 *   php artisan ai:product:diagnose
 *   php artisan ai:product:diagnose --test-spawn  (juga test spawn batch dummy)
 */
class AiProductDiagnose extends Command
{
    protected $signature = 'ai:product:diagnose
                            {--test-spawn : Coba spawn batch dummy untuk test process spawning}
                            {--test-gemini : Test panggil Gemini API real}
                            {--test-supabase : Test koneksi Supabase real}
                            {--test-firebase : Test load produk Firebase}';

    protected $description = 'Diagnostic check untuk fitur AI Product Enrichment + Batch Background.';

    public function handle(): int
    {
        $this->info('═══════════════════════════════════════════════════════');
        $this->info('  AI PRODUCT ASSISTANT — DIAGNOSTIC TOOL');
        $this->info('═══════════════════════════════════════════════════════');
        $this->newLine();

        $allOk = true;

        // ─── 1. Environment ───
        $this->section('1. ENVIRONMENT');
        $this->checkRow('PHP version', PHP_VERSION, version_compare(PHP_VERSION, '8.2.0', '>='), '>= 8.2');
        $this->checkRow('OS', PHP_OS, true);
        $this->checkRow('PHP_BINARY', defined('PHP_BINARY') ? PHP_BINARY : '(undefined)', defined('PHP_BINARY') && file_exists(PHP_BINARY));
        $this->checkRow('Laravel env', app()->environment(), true);
        $this->checkRow('Storage logs path', storage_path('logs'), is_dir(storage_path('logs')) && is_writable(storage_path('logs')), 'must be writable');
        $this->checkRow('Base path artisan', base_path('artisan'), file_exists(base_path('artisan')) && is_readable(base_path('artisan')));
        $this->newLine();

        // ─── 2. Config & Credentials ───
        $this->section('2. CONFIG & CREDENTIALS');
        $geminiKey = (string) config('ai_product_assistant.gemini_api_key', '');
        $supaUrl = (string) config('ai_product_assistant.supabase_url', '');
        $supaKey = (string) config('ai_product_assistant.supabase_service_role_key', '');

        $this->checkRow('GEMINI_API_KEY', $geminiKey ? '••••••'.substr($geminiKey, -6) : '(EMPTY)', $geminiKey !== '');
        $this->checkRow('SUPABASE_URL', $supaUrl ?: '(EMPTY)', $supaUrl !== '' && str_starts_with($supaUrl, 'https://'));
        $this->checkRow('SUPABASE_SERVICE_ROLE_KEY', $supaKey ? '••••••'.substr($supaKey, -6) : '(EMPTY)', $supaKey !== '');
        $this->checkRow('FIREBASE_DATABASE_URL', env('FIREBASE_DATABASE_URL') ?: '(EMPTY)', (bool) env('FIREBASE_DATABASE_URL'));
        $this->checkRow('FIREBASE_CREDENTIALS', env('FIREBASE_CREDENTIALS') ?: '(EMPTY)', $this->checkFirebaseCreds());
        $this->checkRow('Embedding model', config('ai_product_assistant.gemini_embedding_model'), true);
        $this->checkRow('Chat model', config('ai_product_assistant.gemini_chat_model'), true);
        $this->checkRow('Embedding dim', (string) config('ai_product_assistant.gemini_embedding_dimension'), config('ai_product_assistant.gemini_embedding_dimension') == 768, 'must be 768 for HNSW');
        $this->newLine();

        // ─── 3. Database & Tables ───
        $this->section('3. MYSQL TABLES');
        try {
            $hasJobsTable = \Schema::hasTable('ai_product_enrichment_jobs');
            $hasBatchesTable = \Schema::hasTable('ai_product_enrichment_batches');
            $hasLogsTable = \Schema::hasTable('ai_product_enrichment_logs');
            $this->checkRow('ai_product_enrichment_jobs', $hasJobsTable ? 'exists' : 'MISSING', $hasJobsTable);
            $this->checkRow('ai_product_enrichment_batches', $hasBatchesTable ? 'exists' : 'MISSING', $hasBatchesTable);
            $this->checkRow('ai_product_enrichment_logs', $hasLogsTable ? 'exists' : 'MISSING', $hasLogsTable);
            if ($hasJobsTable) {
                $jobsCount = \DB::table('ai_product_enrichment_jobs')->count();
                $this->checkRow('jobs row count', (string) $jobsCount, true);
            }
            if ($hasBatchesTable) {
                $batchesCount = \DB::table('ai_product_enrichment_batches')->count();
                $running = \DB::table('ai_product_enrichment_batches')->whereIn('status', ['queued','running'])->count();
                $this->checkRow('batches total', (string) $batchesCount, true);
                $this->checkRow('batches running/queued', (string) $running, $running <= 2, $running > 2 ? 'ada batch stuck?' : '');
            }
        } catch (\Throwable $e) {
            $this->checkRow('Database connection', 'FAILED: '.$e->getMessage(), false);
            $allOk = false;
        }
        $this->newLine();

        // ─── 4. Process Spawn Capability ───
        $this->section('4. PROCESS SPAWN CAPABILITY');
        $isWindows = strncasecmp(PHP_OS, 'WIN', 3) === 0;
        $this->line('  OS: '.($isWindows ? 'Windows' : 'Linux/Mac'));

        $disabled = explode(',', (string) ini_get('disable_functions'));
        $disabled = array_map('trim', $disabled);
        $popenOk = function_exists('popen') && ! in_array('popen', $disabled, true);
        $shellOk = function_exists('shell_exec') && ! in_array('shell_exec', $disabled, true);
        $procOpenOk = function_exists('proc_open') && ! in_array('proc_open', $disabled, true);
        $this->checkRow('popen()', $popenOk ? 'available' : 'DISABLED', $popenOk);
        $this->checkRow('shell_exec()', $shellOk ? 'available' : 'DISABLED', $shellOk);
        $this->checkRow('proc_open()', $procOpenOk ? 'available' : 'DISABLED', $procOpenOk, 'fallback');

        if (! $isWindows && ! $shellOk) {
            $this->error('  ⚠️  Linux server butuh shell_exec untuk spawn nohup background process.');
            $this->error('  ⚠️  Cek php.ini → disable_functions, hapus shell_exec dari list.');
            $allOk = false;
        }
        if ($isWindows && ! $popenOk) {
            $this->error('  ⚠️  Windows butuh popen untuk spawn cmd /C start /B.');
            $allOk = false;
        }
        $this->newLine();

        // ─── 5. Recent Batch Errors ───
        $this->section('5. RECENT BATCHES (last 5)');
        try {
            $recent = AiProductEnrichmentBatch::orderByDesc('id')->limit(5)->get();
            if ($recent->isEmpty()) {
                $this->line('  (no batches yet)');
            } else {
                foreach ($recent as $b) {
                    $emoji = match ($b->status) {
                        'completed' => '✅', 'running' => '🔄', 'queued' => '⏳',
                        'failed' => '❌', 'cancelled' => '⊘', default => '?',
                    };
                    $this->line(sprintf(
                        '  %s #%d [%s] %s — %d/%d (✓%d ✗%d) — %s',
                        $emoji, $b->id, $b->mode, $b->status,
                        $b->processed_items, $b->total_items,
                        $b->success_count, $b->failed_count,
                        mb_strimwidth($b->last_message ?? '-', 0, 80, '...')
                    ));
                    if ($b->status === 'failed' || ($b->status === 'queued' && $b->created_at && $b->created_at->diffInMinutes(now()) > 5)) {
                        $this->warn(sprintf('    ↳ stale/failed - check log: storage/logs/ai-batch-%s.log', $b->created_at->format('Y-m-d')));
                    }
                }
            }
        } catch (\Throwable $e) {
            $this->error('  Failed to read batches: '.$e->getMessage());
        }
        $this->newLine();

        // ─── 6. Log Files ───
        $this->section('6. LOG FILES');
        $logDir = storage_path('logs');
        $files = glob($logDir.'/ai-batch-*.log');
        if (! $files) {
            $this->warn('  Belum ada file log ai-batch-*.log. Mungkin spawn belum pernah jalan.');
        } else {
            rsort($files);
            foreach (array_slice($files, 0, 3) as $f) {
                $size = filesize($f);
                $age = round((time() - filemtime($f)) / 60);
                $this->line(sprintf('  %s (%s, %d menit lalu)', basename($f), $this->formatSize($size), $age));
                // Tail terakhir
                $tail = $this->tailFile($f, 5);
                if ($tail) {
                    foreach (explode("\n", $tail) as $line) {
                        if (trim($line) !== '') {
                            $this->line('    │ '.mb_strimwidth($line, 0, 120, '...'));
                        }
                    }
                }
            }
        }
        $this->newLine();

        // ─── 7. OPTIONAL: real API tests ───
        if ($this->option('test-firebase')) {
            $this->section('7a. TEST FIREBASE');
            try {
                $start = microtime(true);
                $products = app(FirebaseService::class)->getActiveProducts();
                $ms = round((microtime(true) - $start) * 1000);
                $this->checkRow('getActiveProducts()', count($products).' products in '.$ms.'ms', count($products) > 0);
            } catch (\Throwable $e) {
                $this->checkRow('getActiveProducts()', 'FAILED: '.$e->getMessage(), false);
                $allOk = false;
            }
            $this->newLine();
        }

        if ($this->option('test-gemini')) {
            $this->section('7b. TEST GEMINI');
            try {
                $start = microtime(true);
                $emb = app(GeminiService::class)->embed('test koneksi gemini');
                $ms = round((microtime(true) - $start) * 1000);
                $this->checkRow('Gemini embed()', $emb ? 'OK ('.count($emb).' dims, '.$ms.'ms)' : 'NULL', $emb !== null);
                if (! $emb) {
                    $allOk = false;
                }
            } catch (\Throwable $e) {
                $this->checkRow('Gemini embed()', 'FAILED: '.$e->getMessage(), false);
                $allOk = false;
            }
            $this->newLine();
        }

        if ($this->option('test-supabase')) {
            $this->section('7c. TEST SUPABASE');
            try {
                $start = microtime(true);
                $count = app(SupabaseVectorService::class)->countApproved();
                $ms = round((microtime(true) - $start) * 1000);
                $this->checkRow('Supabase countApproved()', $count.' approved vectors ('.$ms.'ms)', true);
            } catch (\Throwable $e) {
                $this->checkRow('Supabase countApproved()', 'FAILED: '.$e->getMessage(), false);
                $allOk = false;
            }
            $this->newLine();
        }

        if ($this->option('test-spawn')) {
            $this->section('7d. TEST SPAWN BACKGROUND PROCESS');
            $logFile = storage_path('logs/diagnose-spawn-'.time().'.log');
            $isWindows = strncasecmp(PHP_OS, 'WIN', 3) === 0;
            $phpBin = defined('PHP_BINARY') ? PHP_BINARY : 'php';
            $artisan = base_path('artisan');
            // Simple command yang harus selesai cepat tapi jalan di subprocess
            $testCmd = sprintf('%s %s --version', escapeshellarg($phpBin), escapeshellarg($artisan));
            $this->line('  Command: '.$testCmd);
            $this->line('  Log file: '.$logFile);

            try {
                if ($isWindows) {
                    $full = sprintf('cmd /C start /B "" %s >> %s 2>&1', $testCmd, escapeshellarg($logFile));
                    $proc = popen($full, 'r');
                    if ($proc !== false) {
                        pclose($proc);
                    }
                } else {
                    $full = sprintf('nohup %s >> %s 2>&1 & echo $!', $testCmd, escapeshellarg($logFile));
                    shell_exec($full);
                }
                sleep(2); // Tunggu subprocess selesai
                $this->checkRow('Spawn executed', 'OK', true);
                $this->checkRow('Log file created', file_exists($logFile) ? 'YES ('.filesize($logFile).' bytes)' : 'NO', file_exists($logFile));
                if (file_exists($logFile) && filesize($logFile) > 0) {
                    $content = file_get_contents($logFile);
                    $this->line('  Content excerpt:');
                    foreach (explode("\n", trim($content)) as $line) {
                        $this->line('    │ '.$line);
                    }
                    if (str_contains($content, 'Laravel Framework') || str_contains($content, 'Laravel') || preg_match('/\d+\.\d+\.\d+/', $content)) {
                        $this->info('  ✓ Spawn berhasil eksekusi artisan command');
                    } else {
                        $this->warn('  ⚠️  Output tidak seperti yang diharapkan. Cek isi log.');
                    }
                } else {
                    $this->error('  ✗ Log file kosong atau tidak terbentuk. Spawn GAGAL.');
                    $allOk = false;
                }
                @unlink($logFile);
            } catch (\Throwable $e) {
                $this->error('  Spawn exception: '.$e->getMessage());
                $allOk = false;
            }
            $this->newLine();
        }

        // ─── Summary ───
        $this->section('SUMMARY');
        if ($allOk) {
            $this->info('  ✅ Semua check yang dijalankan PASS.');
            $this->newLine();
            $this->line('  Untuk test lebih dalam, jalankan dengan flag:');
            $this->line('    php artisan ai:product:diagnose --test-firebase --test-gemini --test-supabase --test-spawn');
        } else {
            $this->error('  ❌ Ada check yang FAIL. Cek output di atas.');
        }
        $this->newLine();

        return $allOk ? self::SUCCESS : self::FAILURE;
    }

    protected function section(string $title): void
    {
        $this->line('▎ '.$title);
        $this->line('  '.str_repeat('─', max(40, strlen($title))));
    }

    protected function checkRow(string $label, string $value, bool $ok, string $note = ''): void
    {
        $emoji = $ok ? '✅' : '❌';
        $this->line(sprintf('  %s %-32s  %s%s', $emoji, $label, $value, $note ? '  ('.$note.')' : ''));
    }

    protected function checkFirebaseCreds(): bool
    {
        $path = env('FIREBASE_CREDENTIALS');
        if (! $path) {
            return false;
        }
        // Resolve relatif terhadap project root
        if (! str_starts_with($path, '/') && $path[1] !== ':') {
            $path = base_path($path);
        }

        return is_readable($path);
    }

    protected function tailFile(string $path, int $lines = 10): string
    {
        if (! is_readable($path)) {
            return '';
        }
        $size = filesize($path);
        if ($size === 0) {
            return '';
        }
        $f = fopen($path, 'rb');
        if (! $f) {
            return '';
        }
        $maxRead = min($size, 8192);
        fseek($f, -$maxRead, SEEK_END);
        $content = fread($f, $maxRead);
        fclose($f);
        $allLines = explode("\n", trim((string) $content));

        return implode("\n", array_slice($allLines, -$lines));
    }

    protected function formatSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.'B';
        }
        if ($bytes < 1048576) {
            return round($bytes / 1024, 1).'KB';
        }

        return round($bytes / 1048576, 1).'MB';
    }
}
