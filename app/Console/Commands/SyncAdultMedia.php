<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Models\Store;
use Illuminate\Console\Command;

class SyncAdultMedia extends Command
{
    protected $signature = 'adult:sync-media {file : Path to lovedoll CSV} {--store=adult}';
    protected $description = 'Attach images from CSV to products that have none';

    public function handle(): int
    {
        $file = $this->argument('file');
        $store = Store::where('code', $this->option('store'))->first();
        if (!$store || !file_exists($file)) {
            $this->error('Store or file missing');
            return 1;
        }

        $handle = fopen($file, 'r');
        $headers = fgetcsv($handle);
        $attached = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $data = array_combine($headers, array_pad($row, count($headers), ''));
            $sku = trim($data['sku'] ?? '');
            if (!$sku) continue;

            $variant = ProductVariant::where('store_id', $store->id)->where('sku', $sku)->first();
            if (!$variant) continue;

            $hasMedia = ProductMedia::where('product_id', $variant->product_id)->exists();
            if ($hasMedia) continue;

            $images = array_filter(array_map('trim', explode('|', $data['images'] ?? '')));
            foreach (array_values($images) as $i => $url) {
                if (!str_starts_with($url, 'http')) continue;
                ProductMedia::create([
                    'store_id' => $store->id,
                    'product_id' => $variant->product_id,
                    'storage_disk' => 'url',
                    'storage_path' => $url,
                    'is_primary' => $i === 0,
                    'sort_order' => $i,
                ]);
            }
            $attached++;
        }

        fclose($handle);
        $this->info("Attached media to {$attached} products.");
        return 0;
    }
}
