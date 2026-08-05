<?php

declare(strict_types=1);

namespace App\Application\UseCases\Product;

use App\Domain\Repositories\ProductRepositoryInterface;

final class ListProductsUseCase
{
    public function __construct(
        private ProductRepositoryInterface $productRepository
    ) {}

    /** @return list<array<string, mixed>> */
    public function execute(): array
    {
        $products = $this->productRepository->findAll();

        return array_map(fn ($product) => $product->toArray(), $products);
    }
}
