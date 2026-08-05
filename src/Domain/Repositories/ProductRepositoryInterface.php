<?php

declare(strict_types=1);

namespace App\Domain\Repositories;

use App\Domain\Entities\Product;

interface ProductRepositoryInterface
{
    /** @return list<Product> */
    public function findAll(): array;

    public function save(Product $product): Product;
}
