<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Store;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AdultCategorySeeder extends Seeder
{
    /**
     * Main categories from the lovedollfrance.com homepage tiles.
     */
    private array $categories = [
        'Grosses BBW',
        'Japonaises',
        'Grosse poitrine',
        'Noire & Black',
        'Caucasiennes (Blanches)',
        'Animé & Hentai',
        'Sex doll en TPE',
        'Sex doll en silicone',
        'Sex doll fantaisistes',
    ];

    public function run(): void
    {
        $store = Store::where('code', 'adult')->first();

        if (!$store) {
            $this->command->error('Adult store not found. Seed stores first.');
            return;
        }

        $sort = 0;
        foreach ($this->categories as $name) {
            Category::updateOrCreate(
                [
                    'store_id' => $store->id,
                    'slug' => Str::slug($name),
                ],
                [
                    'parent_id' => null,
                    'name' => $name,
                    'sort_order' => $sort++,
                    'is_active' => true,
                ]
            );
        }

        $this->command->info(count($this->categories) . " categories seeded for adult store.");
    }
}
