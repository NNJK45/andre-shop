<?php

namespace Database\Seeders;

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Supplier\Models\Supplier;
use App\Domain\User\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@andreshop.cm'],
            ['name' => 'Administrateur André Shop', 'password' => Hash::make('password'), 'email_verified_at' => now()],
        );
        $admin->forceFill(['role' => UserRole::Admin])->save();

        foreach ([
            ['name' => 'Client Démo', 'email' => 'client@andreshop.cm'],
            ['name' => 'Responsable Hôtel Démo', 'email' => 'hotel@andreshop.cm'],
            ['name' => 'Revendeur Démo', 'email' => 'revendeur@andreshop.cm'],
        ] as $customer) {
            $user = User::updateOrCreate(
                ['email' => $customer['email']],
                ['name' => $customer['name'], 'password' => Hash::make('password'), 'email_verified_at' => now()],
            );
            $user->forceFill(['role' => UserRole::Customer])->save();
        }

        $categories = [];
        foreach ([
            ['name' => 'Maison & Cuisine', 'slug' => 'maison-cuisine', 'description' => 'Équipements et essentiels pour la maison et la cuisine.'],
            ['name' => 'Équipement professionnel', 'slug' => 'equipement-professionnel', 'description' => 'Solutions fiables pour hôtels, restaurants, écoles et entreprises.'],
            ['name' => 'Électronique', 'slug' => 'electronique', 'description' => 'Accessoires et appareils utiles au quotidien.'],
            ['name' => 'Bureau & Scolaire', 'slug' => 'bureau-scolaire', 'description' => 'Tout pour travailler, apprendre et organiser vos espaces.'],
            ['name' => 'Hygiène & Entretien', 'slug' => 'hygiene-entretien', 'description' => 'Produits et équipements pour des espaces propres et sains.'],
            ['name' => 'Mobilier', 'slug' => 'mobilier', 'description' => 'Mobilier pratique et durable pour particuliers et professionnels.'],
        ] as $data) {
            $categories[$data['slug']] = Category::updateOrCreate(['slug' => $data['slug']], [...$data, 'is_active' => true]);
        }

        $products = [
            ['category' => 'maison-cuisine', 'name' => 'Blender professionnel 2 L', 'slug' => 'blender-professionnel-2l', 'sku' => 'AND-BLD-001', 'price' => 48500, 'description' => 'Blender robuste pour smoothies, jus et préparations intensives.'],
            ['category' => 'maison-cuisine', 'name' => 'Batterie de cuisine 10 pièces', 'slug' => 'batterie-cuisine-10-pieces', 'sku' => 'AND-CUI-002', 'price' => 62000, 'description' => 'Ensemble complet et durable pour équiper une cuisine familiale ou professionnelle.'],
            ['category' => 'maison-cuisine', 'name' => 'Réfrigérateur compact 90 L', 'slug' => 'refrigerateur-compact-90l', 'sku' => 'AND-FRG-003', 'price' => 179000, 'description' => 'Format compact, faible consommation et rangement optimisé.'],
            ['category' => 'equipement-professionnel', 'name' => 'Machine à café automatique', 'slug' => 'machine-cafe-automatique', 'sku' => 'AND-CAF-004', 'price' => 285000, 'description' => 'Machine à café pensée pour bureaux, hôtels et espaces d’accueil.'],
            ['category' => 'equipement-professionnel', 'name' => 'Four électrique convection 60 L', 'slug' => 'four-electrique-convection-60l', 'sku' => 'AND-FOU-005', 'price' => 235000, 'description' => 'Cuisson homogène et capacité adaptée aux petites cuisines professionnelles.'],
            ['category' => 'equipement-professionnel', 'name' => 'Distributeur d’eau sur pied', 'slug' => 'distributeur-eau-sur-pied', 'sku' => 'AND-EAU-006', 'price' => 119000, 'description' => 'Distributeur chaud et froid pour bureaux, écoles et espaces collectifs.'],
            ['category' => 'electronique', 'name' => 'Onduleur 1200 VA', 'slug' => 'onduleur-1200va', 'sku' => 'AND-OND-007', 'price' => 79000, 'description' => 'Protection fiable pour ordinateur, box internet et équipements sensibles.'],
            ['category' => 'electronique', 'name' => 'Lampe LED rechargeable', 'slug' => 'lampe-led-rechargeable', 'sku' => 'AND-LED-008', 'price' => 12500, 'description' => 'Lampe nomade rechargeable, idéale pour la maison et les déplacements.'],
            ['category' => 'bureau-scolaire', 'name' => 'Imprimante multifonction Wi-Fi', 'slug' => 'imprimante-multifonction-wifi', 'sku' => 'AND-IMP-009', 'price' => 99000, 'description' => 'Impression, scan et copie pour bureaux, écoles et petites entreprises.'],
            ['category' => 'bureau-scolaire', 'name' => 'Kit bureau ergonomique', 'slug' => 'kit-bureau-ergonomique', 'sku' => 'AND-BUR-010', 'price' => 42000, 'description' => 'Support ordinateur, tapis et accessoires pour un poste confortable.'],
            ['category' => 'hygiene-entretien', 'name' => 'Aspirateur eau et poussière', 'slug' => 'aspirateur-eau-poussiere', 'sku' => 'AND-ASP-011', 'price' => 87500, 'description' => 'Aspirateur polyvalent pour maison, atelier, hôtel ou commerce.'],
            ['category' => 'mobilier', 'name' => 'Chaise visiteur empilable', 'slug' => 'chaise-visiteur-empilable', 'sku' => 'AND-CHA-012', 'price' => 18500, 'description' => 'Chaise solide et facile à ranger pour salles d’attente et salles de classe.'],
        ];

        foreach ($products as $data) {
            $product = Product::updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'category_id' => $categories[$data['category']]->id,
                    'name' => $data['name'],
                    'sku' => $data['sku'],
                    'description' => $data['description'],
                    'price' => $data['price'],
                    'is_active' => true,
                ],
            );

            $product->variants()->updateOrCreate(
                ['sku' => $data['sku'].'-STD'],
                ['name' => 'Standard', 'price' => $data['price'], 'attributes' => ['condition' => 'neuf'], 'is_active' => true],
            );

            $inventory = InventoryItem::firstOrNew([
                'stockable_type' => Product::class,
                'stockable_id' => $product->id,
            ]);
            $inventory->on_hand = 12;
            $inventory->reserved = 0;
            $inventory->reorder_level = 3;
            $inventory->save();
        }

        foreach ([
            ['name' => 'Cameroon Equipment Supply', 'contact_name' => 'Nadine Tchana', 'email' => 'contact@ces-demo.cm', 'phone' => '+237690000001', 'address' => 'Douala, Littoral', 'notes' => 'Fournisseur démonstration équipements professionnels.'],
            ['name' => 'Maison & Pro Distribution', 'contact_name' => 'Arnaud Foko', 'email' => 'bonjour@mpd-demo.cm', 'phone' => '+237690000002', 'address' => 'Yaoundé, Centre', 'notes' => 'Fournisseur démonstration maison et bureau.'],
        ] as $supplier) {
            Supplier::updateOrCreate(['email' => $supplier['email']], [...$supplier, 'is_active' => true]);
        }
    }
}
