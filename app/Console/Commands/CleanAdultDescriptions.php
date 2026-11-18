<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductAttributeDefinition;
use App\Models\ProductAttributeValue;
use App\Models\Store;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CleanAdultDescriptions extends Command
{
    protected $signature = 'adult:clean-descriptions {--store=adult}';
    protected $description = 'Extract specs from descriptions and clean them';

    public function handle(): int
    {
        $store = Store::where('code', $this->option('store'))->first();
        if (!$store) {
            $this->error('Store not found');
            return 1;
        }

        $defs = $this->ensureDefs($store);
        $products = Product::where('store_id', $store->id)->get();
        $cleaned = 0;

        foreach ($products as $product) {
            $desc = $product->description ?? '';
            if (strlen($desc) < 100) continue;

            $specs = $this->extractSpecs($desc);
            if (!$specs) continue;

            // Save as attributes
            foreach ($specs as $code => $value) {
                if (!isset($defs[$code]) || !$value) continue;
                ProductAttributeValue::updateOrCreate(
                    [
                        'store_id' => $store->id,
                        'product_id' => $product->id,
                        'attribute_definition_id' => $defs[$code]->id,
                    ],
                    ['value_text' => $value]
                );
            }

            // Clean description
            $clean = $this->cleanDescription($desc);
            if ($clean !== $desc) {
                $product->description = $clean;
                $product->save();
                $cleaned++;
            }
        }

        $this->info("Cleaned {$cleaned} descriptions.");
        return 0;
    }

    private function ensureDefs(Store $store): array
    {
        $list = [
            'hauteur' => 'Hauteur',
            'poids' => 'Poids',
            'tour_taille' => 'Tour de taille',
            'hanches' => 'Hanches',
            'bonnet' => 'Bonnet',
            'materiau' => 'Matériau',
        ];
        $defs = [];
        $sort = 100;
        foreach ($list as $code => $label) {
            $defs[$code] = ProductAttributeDefinition::updateOrCreate(
                ['store_id' => $store->id, 'code' => $code],
                ['label' => $label, 'data_type' => 'text', 'is_filterable' => true, 'sort_order' => $sort++]
            );
        }
        return $defs;
    }

    private function extractSpecs(string $desc): array
    {
        $specs = [];
        if (preg_match('/Hauteur\s*:?\s*(\d+\s*cm)/i', $desc, $m)) {
            $specs['hauteur'] = trim($m[1]);
        }
        if (preg_match('/Poids\s*:?\s*(\d+\s*kg)/i', $desc, $m)) {
            $specs['poids'] = trim($m[1]);
        }
        if (preg_match('/Tour de taille\s*:?\s*(\d+\s*cm)/i', $desc, $m)) {
            $specs['tour_taille'] = trim($m[1]);
        }
        if (preg_match('/Hanches\s*:?\s*(\d+\s*cm)/i', $desc, $m)) {
            $specs['hanches'] = trim($m[1]);
        }
        if (preg_match('/(?:Seins|Bonnet|Poitrine)\s*:?\s*([A-Z]-?Cup|Bonnet\s*[A-Z])/i', $desc, $m)) {
            $specs['bonnet'] = trim($m[1]);
        }
        if (preg_match('/\b(TPE|Silicone)\b/i', $desc, $m)) {
            $specs['materiau'] = strtoupper($m[1]);
        }
        return $specs;
    }

    private function cleanDescription(string $desc): string
    {
        // Remove the specs block starting from "Dimensions" or "Hauteur:"
        $clean = preg_replace('/\s*(Dimensions|Hauteur\s*:).*$/is', '', $desc);
        $clean = preg_replace('/\s*(Matériaux|Orifices sexuels|Livraison discrète).*$/is', '', $clean);
        $clean = preg_replace('/\s+/', ' ', $clean);
        return trim($clean);
    }
}
