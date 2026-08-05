<?php

declare(strict_types=1);

namespace App\Database\Seeders;

use App\Domain\Entities\Product;
use App\Infrastructure\Database\DB;
use App\Infrastructure\Repositories\PDOProductRepository;

/**
 * Seeds sample products. Idempotent: skips if products table already has rows.
 */
final class ProductSeeder
{
    public function run(): void
    {
        $pdo = DB::connection();
        $repo = new PDOProductRepository($pdo);

        $count = (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
        if ($count > 0) {
            echo "products already seeded ({$count} rows) — skip\n";
            return;
        }

        $samples = [
            new Product(null, 'Mithril Ingot', 'Raw mithril for the forges of Erebor', 99.90, 'MITH-001'),
            new Product(null, 'Durin Hammer', 'Forging hammer of the deep halls', 149.50, 'DUR-HAM-01'),
            new Product(null, 'Eregion Gate Key', 'Opens the outer gates of Eregion', 42.00, 'ERG-KEY-1'),
        ];

        foreach ($samples as $product) {
            $repo->save($product);
            echo "seeded product: {$product->sku}\n";
        }
    }
}
