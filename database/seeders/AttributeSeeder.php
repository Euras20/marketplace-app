<?php

namespace Database\Seeders;

use App\Models\Store;
use App\Models\ProductAttributeDefinition;
use Illuminate\Database\Seeder;

class AttributeSeeder extends Seeder
{
    public function run(): void
    {
        $agri = Store::where('code', 'agri')->first();
        $adult = Store::where('code', 'adult')->first();

        if ($agri) {
            $this->seed($agri->id, [
                ['code' => 'puissance', 'label' => 'Puissance', 'data_type' => 'text', 'is_filterable' => true],
                ['code' => 'poids', 'label' => 'Poids', 'data_type' => 'text', 'is_filterable' => true],
                ['code' => 'capacite', 'label' => 'Capacité', 'data_type' => 'text', 'is_filterable' => true],
                ['code' => 'dimensions', 'label' => 'Dimensions', 'data_type' => 'text', 'is_filterable' => false],
                ['code' => 'moteur', 'label' => 'Moteur', 'data_type' => 'text', 'is_filterable' => true],
                ['code' => 'carburant', 'label' => 'Carburant', 'data_type' => 'text', 'is_filterable' => true],
                ['code' => 'garantie', 'label' => 'Garantie', 'data_type' => 'text', 'is_filterable' => false],
            ]);
        }

        if ($adult) {
            $this->seed($adult->id, [
                ['code' => 'materiau', 'label' => 'Matériau', 'data_type' => 'text', 'is_filterable' => true],
                ['code' => 'taille', 'label' => 'Taille', 'data_type' => 'text', 'is_filterable' => true],
                ['code' => 'couleur', 'label' => 'Couleur', 'data_type' => 'text', 'is_filterable' => true],
                ['code' => 'poids', 'label' => 'Poids', 'data_type' => 'text', 'is_filterable' => false],
                ['code' => 'dimensions', 'label' => 'Dimensions', 'data_type' => 'text', 'is_filterable' => false],
            ]);
        }
    }

    private function seed(int $storeId, array $defs): void
    {
        foreach ($defs as $i => $d) {
            ProductAttributeDefinition::updateOrCreate(
                ['store_id' => $storeId, 'code' => $d['code']],
                [
                    'label' => $d['label'],
                    'data_type' => $d['data_type'],
                    'is_filterable' => $d['is_filterable'],
                    'is_required' => false,
                    'sort_order' => $i,
                ]
            );
        }
    }
}
