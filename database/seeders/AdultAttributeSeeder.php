<?php

namespace Database\Seeders;

use App\Models\ProductAttributeDefinition;
use App\Models\Store;
use Illuminate\Database\Seeder;

class AdultAttributeSeeder extends Seeder
{
    /**
     * Attribute definitions for the adult store.
     * code => [label, filterable]
     */
    private array $attributes = [
        'couleur_cheveux' => ['Couleur de cheveux', true],
        'materiau' => ['Matériau', true],
        'hauteur' => ['Hauteur', true],
        'morphologie' => ['Morphologie', true],
        'style' => ['Style', true],
        'accessoires' => ['Accessoires', false],
        'poids' => ['Poids', false],
        'bonnet' => ['Bonnet', true],
        'origine' => ['Origine', true],
    ];

    public function run(): void
    {
        $store = Store::where('code', 'adult')->first();

        if (!$store) {
            $this->command->error('Adult store not found.');
            return;
        }

        $sort = 0;
        foreach ($this->attributes as $code => [$label, $filterable]) {
            ProductAttributeDefinition::updateOrCreate(
                [
                    'store_id' => $store->id,
                    'code' => $code,
                ],
                [
                    'label' => $label,
                    'data_type' => 'text',
                    'is_required' => false,
                    'is_filterable' => $filterable,
                    'sort_order' => $sort++,
                ]
            );
        }

        $this->command->info(count($this->attributes) . " attribute definitions seeded for adult store.");
    }
}
