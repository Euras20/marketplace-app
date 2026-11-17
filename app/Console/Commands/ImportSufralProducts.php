<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttributeDefinition;
use App\Models\ProductAttributeValue;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Models\Store;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ImportSufralProducts extends Command
{
    protected $signature = 'import:sufral {file : Path to the products CSV} {--store=agri : Store code}';
    protected $description = 'Import products from the Sufral CSV';

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
        $brands = Brand::where('store_id', $store->id)->get()->keyBy(fn ($b) => Str::slug($b->name));
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

            // Skip if SKU already exists
            if (ProductVariant::where('store_id', $store->id)->where('sku', $sku)->exists()) {
                $skipped++;
                continue;
            }

            // Category from attr "Catégorie"
            $categoryName = $this->findAttr($data, 'Catégorie');
            $category = $categoryName ? $categories->get(Str::slug($categoryName)) : null;

            // Brand
            $brandName = trim($data['brand'] ?? '') ?: $this->findAttr($data, 'Marque');
            $brand = $brandName ? $brands->get(Str::slug($brandName)) : null;

            // Price
            $priceRaw = trim($data['price_ttc'] ?? '') ?: $this->findAttr($data, 'Prix TTC');
            $price = $this->parsePrice($priceRaw);

            $product = Product::create([
                'store_id' => $store->id,
                'category_id' => $category?->id,
                'brand_id' => $brand?->id,
                'name' => $name,
                'slug' => Str::slug($name) . '-' . Str::random(4),
                'description' => trim($data['description'] ?? ''),
                'status' => Product::STATUS_PUBLISHED,
                'published_at' => now(),
            ]);

            ProductVariant::create([
                'store_id' => $store->id,
                'product_id' => $product->id,
                'sku' => $sku,
                'price' => $price,
                'is_active' => true,
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

            // Attributes
            for ($i = 1; $i <= 30; $i++) {
                $aname = trim($data["attr_{$i}_name"] ?? '');
                $aval = trim($data["attr_{$i}_value"] ?? '');
                if ($aname === '' || $aval === '') continue;
                if (in_array($aname, ['Catégorie', 'État', 'Marque', 'Référence', 'Prix TTC', 'Prix HT', 'Disponibilité'])) continue;

                $code = Str::slug($aname, '_');
                if (strlen($code) > 60) $code = substr($code, 0, 60);
                $def = $attrDefs->get($code);
                if (!$def) continue;

                ProductAttributeValue::create([
                    'store_id' => $store->id,
                    'product_id' => $product->id,
                    'attribute_definition_id' => $def->id,
                    'value_text' => $aval,
                ]);
            }

            $created++;
            if ($created % 100 === 0) {
                $this->info("Created {$created}...");
            }
        }

        fclose($handle);
        $this->info("Done. Created: {$created}, Skipped: {$skipped}");
        return 0;
    }

    private function findAttr(array $data, string $name): ?string
    {
        for ($i = 1; $i <= 30; $i++) {
            if (trim($data["attr_{$i}_name"] ?? '') === $name) {
                return trim($data["attr_{$i}_value"] ?? '') ?: null;
            }
        }
        return null;
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
