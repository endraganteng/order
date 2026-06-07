<?php

namespace App\Console\Commands;

use App\Models\RackProduct;
use Illuminate\Console\Command;
use Kreait\Firebase\Contract\Database;

/**
 * SeedRackProductsFromFirebase
 *
 * Baca /rack_products Firebase, tulis ke MySQL. Idempotent via
 * updateOrCreate(firebase_legacy_key). Mirror payload verbatim.
 *
 * Usage:
 *   php artisan seed:rack-products --dry-run
 *   php artisan seed:rack-products
 */
class SeedRackProductsFromFirebase extends Command
{
    protected $signature = 'seed:rack-products
                            {--dry-run : Hitung saja, tidak tulis MySQL}';

    protected $description = 'Seed rack_products dari Firebase RTDB ke MySQL (idempotent)';

    public function handle(Database $database): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info('Seed rack_products dari RTDB'.($dryRun ? ' (DRY-RUN)' : ''));

        $snapshot = $database->getReference('rack_products')->getSnapshot();
        $items = $snapshot->getValue() ?: [];

        if (empty($items)) {
            $this->warn('Tidak ada produk di /rack_products.');
            return Command::SUCCESS;
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($items as $fbKey => $product) {
            if (! is_array($product)) {
                $skipped++;
                continue;
            }

            if ($dryRun) {
                RackProduct::where('firebase_legacy_key', (string) $fbKey)->exists() ? $updated++ : $created++;
                continue;
            }

            $model = RackProduct::updateOrCreate(
                ['firebase_legacy_key' => (string) $fbKey],
                [
                    'name' => (string) ($product['name'] ?? ''),
                    'category_id' => $product['category_id'] ?? null,
                    'standard_qty' => max(0, (int) ($product['standard_qty'] ?? 0)),
                    'unit' => (string) ($product['unit'] ?? 'pcs'),
                    'is_active' => (bool) ($product['is_active'] ?? true),
                    'firebase_payload' => $product,
                    'event_created_at' => $product['created_at'] ?? null,
                    'event_updated_at' => $product['updated_at'] ?? null,
                ]
            );
            $model->wasRecentlyCreated ? $created++ : $updated++;
        }

        $this->info("Selesai. Created: {$created} | Updated: {$updated} | Skipped: {$skipped}");
        return Command::SUCCESS;
    }
}
