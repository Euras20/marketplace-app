<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Store;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Parent → [sub-categories]
     */
    private array $tree = [
        'Énergie' => [
            'Groupes électrogènes',
            'Groupes électrogènes inverter',
            'Stations électriques portables',
            'Batteries et kits solaires',
            'Onduleurs solaires',
            'Bornes de recharge',
        ],
        'Bois de chauffage' => [
            'Fendeuses de bûches',
            'Scies à bûches et combinés',
            'Broyeurs de végétaux',
        ],
        'Chauffage' => [
            'Poêles à bois',
            'Poêles à granulés classiques',
            'Poêles à granulés hydro',
            'Poêles à granulés étanches',
            'Poêles à granulés canalisables',
            'Poêles mixtes bois & granulés',
            'Cuisinières à bois',
            'Chaudières',
            'Conduits de fumée',
            'Foyers & inserts',
        ],
        'Jardin et espaces verts' => [
            'Débroussailleuses',
            'Robots tondeuses',
            'Tondeuses autoportées',
            'Tondeuses',
            'Motoculteurs et motobineuses',
            'Taille-haies et récolte',
            'Souffleurs et balayeuses',
        ],
        'Agricole' => [
            'Gyrobroyeurs et broyeurs à fléaux',
            'Outils attelés',
            'Pulvérisation et atomiseurs',
            'Tracteurs et micro-tracteurs',
            'Abris et tunnels agricoles',
            'Bétaillères et vans',
        ],
        'Remorques' => [
            'Remorques utilitaires',
            'Remorques porte-voitures et plateaux',
            'Remorques basculantes et bennes',
            'Remorques porte-motos',
            'Remorques bâchées',
        ],
        'Déneigement' => [
            'Lames à neige',
            'Fraises à neige',
            'Saleuses et épandeurs',
        ],
        'BTP et manutention' => [
            'Levage et manutention',
            'Chariots élévateurs et transpalettes',
            'Diables et monte-escaliers',
            'Mini-pelles',
            'Mini-pelles électriques',
            'Pelles hydrauliques',
            'Brouettes motorisées et dumpers',
            'Compactage et bétonnage',
            'Chargeuses et télescopiques',
            'Nacelles et plateformes',
        ],
        'Conteneurs et modulaires' => [
            'Conteneurs maritimes',
            'Conteneurs aménagés et modulaires',
            'Accessoires conteneurs',
        ],
        'Garage et atelier' => [
            'Compresseurs',
            'Nettoyeurs haute pression',
            'Nettoyage professionnel',
            'Soudage',
            'Travail du métal',
            'Travail du bois',
            'Équipement garage',
            'Ponts élévateurs',
        ],
        'Machines-outils' => [],
        'Pompes et arrosage' => [
            'Motopompes et pompes',
            'Cuves et récupération d\'eau',
        ],
        'Rangement et atelier' => [
            'Établis et rangement',
        ],
        'Cuisine professionnelle' => [
            'Matériel de cuisine pro',
        ],
        'Divers' => [
            'Autres équipements',
            'Quads et SSV',
            'Pièces détachées',
            'Chauffage',
            'Mini-pelle 1 tonne',
            '0,8 tonne',
            'Remorque bagagère 1 essieu non freinée',
            'Mini-pelle 1,5 tonne',
            'Remorque tribenne basculante 2 essieux freinée',
        ],
    ];

    public function run(): void
    {
        // Target the agri store by default. Change the code if needed.
        $store = Store::where('code', 'agri')->first()
            ?? Store::where('status', 'active')->orderBy('id')->first();

        if (!$store) {
            $this->command->error('No active store found. Seed stores first.');
            return;
        }

        $sort = 0;

        foreach ($this->tree as $parentName => $children) {
            $parent = Category::updateOrCreate(
                [
                    'store_id' => $store->id,
                    'slug' => Str::slug($parentName),
                ],
                [
                    'parent_id' => null,
                    'name' => $parentName,
                    'sort_order' => $sort++,
                    'is_active' => true,
                ]
            );

            $childSort = 0;
            foreach ($children as $childName) {
                Category::updateOrCreate(
                    [
                        'store_id' => $store->id,
                        'slug' => Str::slug($childName),
                    ],
                    [
                        'parent_id' => $parent->id,
                        'name' => $childName,
                        'sort_order' => $childSort++,
                        'is_active' => true,
                    ]
                );
            }
        }

        $this->command->info("Categories seeded for store: {$store->code}");
    }
}
