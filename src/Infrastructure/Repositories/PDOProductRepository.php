<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Entities\Product;
use App\Domain\Repositories\ProductRepositoryInterface;
use App\Infrastructure\Exceptions\InfrastructureException;
use PDO;
use PDOException;

final class PDOProductRepository implements ProductRepositoryInterface
{
    public function __construct(private PDO $db) {}

    public function findAll(): array
    {
        try {
            $stmt = $this->db->query('SELECT * FROM products ORDER BY id ASC');
            $products = [];
            while ($row = $stmt->fetch()) {
                $products[] = $this->mapRow($row);
            }

            return $products;
        } catch (PDOException $e) {
            throw new InfrastructureException('Failed to fetch products', 0, $e);
        }
    }

    public function save(Product $product): Product
    {
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO products (name, description, price, sku) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([
                $product->name,
                $product->description,
                $product->price,
                $product->sku,
            ]);
            $product->id = (int) $this->db->lastInsertId();

            return $product;
        } catch (PDOException $e) {
            throw new InfrastructureException('Failed to save product', 0, $e);
        }
    }

    /** @param array<string, mixed> $row */
    private function mapRow(array $row): Product
    {
        return new Product(
            id: (int) $row['id'],
            name: (string) $row['name'],
            description: (string) ($row['description'] ?? ''),
            price: (float) $row['price'],
            sku: (string) $row['sku'],
        );
    }
}
