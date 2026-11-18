<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductAttributeDefinition;
use App\Models\ProductAttributeValue;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Models\Store;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ImportLovedollProducts extends Command
{
    protected $signature = 'import:lovedoll {file : Path to the lovedoll CSV} {--store=adult : Store code}';
    protected $description = 'Import products from the LoveDollFrance CSV';

    /**
     * Map CSV category keywords to our main category names.
     */
    private array $categoryMap = [
        'bbw' => 'Grosses BBW',
        'grosses bbw' => 'Grosses BBW',
        'japonais' => 'Japonaises',
        'grosse poitrine' => 'Grosse poitrine',
        'black' => 'Noire & Black',
        'noire' => 'Noire & Black',
        'caucasien' => 'Caucasiennes (Blanches)',
        'blanche' => 'Caucasiennes (Blanches)',
        'anime' => 'Animé & Hentai',
        'hentai' => 'Animé & Hentai',
        'tpe' => 'Sex doll en TPE',
        'silicone' => 'Sex doll en silicone',
        'fantaisie' => 'Sex doll fantaisistes',
        'fantasy' => 'Sex doll fantaisistes',
    ];

    public function handle(): int
    {
        $file = $this->argument('file');
        $storeCode = $this->option('store');

        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            return 1;
        }

        $store = Store::where('code', $storeCode)->first();
        if (!$store) {
            $this->error("Store not found: {$storeCode}");
            return 1;
        }

        $categories = Category::where('store_id', $store->id)->get()->keyBy(fn ($c) => Str::slug($c->name));
        $attrDefs = ProductAttributeDefinition::where('store_id', $store->id)->get()->keyBy('code');

        $handle = fopen($file, 'r');
        $headers = fgetcsv($handle);
        if (!$headers) {
            $this->error('Empty CSV');
            return 1;
        }

        $created = 0;
        $skipped = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $data = array_combine($headers, array_pad($row, count($headers), ''));
            if (!$data) continue;

            $name = trim($data['name'] ?? '');
            $sku = trim($data['sku'] ?? '');
            if ($name === '' || $sku === '') {
                $skipped++;
                continue;
            }

            if (ProductVariant::where('store_id', $store->id)->where('sku', $sku)->exists()) {
                $skipped++;
                continue;
            }

            // Pick main category
            $csvCats = array_map('trim', explode('|', $data['categories'] ?? ''));
            $mainCategory = $this->matchCategory($csvCats, $categories);

            $price = $this->parsePrice($data['price'] ?? $data['regular_price'] ?? '0');
            $description = trim($data['description'] ?? '') ?: trim($data['short_description'] ?? '');

            $product = Product::create([
                'store_id' => $store->id,
                'category_id' => $mainCategory?->id,
                'name' => $name,
                'slug' => Str::slug($name) . '-' . Str::random(4),
                'description' => $description,
                'status' => Product::STATUS_PUBLISHED,
                'published_at' => now(),
            ]);

            $variant = ProductVariant::create([
                'store_id' => $store->id,
                'product_id' => $product->id,
                'sku' => $sku,
                'price' => $price,
                'is_active' => true,
            ]);

            Inventory::create([
                'store_id' => $store->id,
                'product_variant_id' => $variant->id,
                'on_hand' => 2000,
                'reserved' => 0,
            ]);

            // Images
            $images = array_filter(array_map('trim', explode('|', $data['images'] ?? '')));
            foreach (array_values($images) as $i => $url) {
                if (!str_starts_with($url, 'http')) continue;
                ProductMedia::create([
                    'store_id' => $store->id,
                    'product_id' => $product->id,
                    'storage_disk' => 'url',
                    'storage_path' => $url,
                    'is_primary' => $i === 0,
                    'sort_order' => $i,
                ]);
            }

            // Extra categories as attributes
            $this->saveAttributes($store, $product, $csvCats, $attrDefs);

            $created++;
            if ($created % 50 === 0) {
                $this->info("Created {$created}...");
            }
        }

        fclose($handle);
        $this->info("Done. Created: {$created}, Skipped: {$skipped}");
        return 0;
    }

    private function matchCategory(array $csvCats, $categories): ?Category
    {
        foreach ($csvCats as $csvCat) {
            $lower = mb_strtolower($csvCat);
            foreach ($this->categoryMap as $keyword => $mainName) {
                if (str_contains($lower, $keyword)) {
                    $slug = Str::slug($mainName);
                    if ($categories->has($slug)) {
                        return $categories->get($slug);
                    }
                }
            }
        }
        // Fallback
        return $categories->get(Str::slug('Sex doll en TPE')) ?? $categories->first();
    }

    private function saveAttributes($store, $product, array $csvCats, $attrDefs): void
    {
        $mainSlugs = array_map(fn ($n) => Str::slug($n), array_values($this->categoryMap));
        $extras = [];
        foreach ($csvCats as $csvCat) {
            $slug = Str::slug($csvCat);
            if (!in_array($slug, $mainSlugs) && $csvCat !== '') {
                $extras[] = $csvCat;
            }
        }
        if (!$extras) return;

        $code = 'extra_categories';
        $def = $attrDefs->get($code);
        if (!$def) {
            $def = ProductAttributeDefinition::create([
                'store_id' => $store->id,
                'code' => $code,
                'label' => 'Catégories supplémentaires',
                'data_type' => 'text',
                'is_required' => false,
                'is_filterable' => true,
                'sort_order' => 0,
            ]);
            $attrDefs->put($code, $def);
        }

        ProductAttributeValue::updateOrCreate(
            [
                'store_id' => $store->id,
                'product_id' => $product->id,
                'attribute_definition_id' => $def->id,
            ],
            [
                'value_text' => implode(' | ', $extras),
            ]
        );
    }

    private function parsePrice(?string $raw): float
    {
        if (!$raw) return 0;
        $clean = preg_replace('/[^\d,.]/', '', $raw);
        $clean = str_replace([' ', "\u{202f}", "\u{00a0}"], '', $clean);
        $clean = str_replace(',', '.', $clean);
        return (float) $clean;
    }
}
