<?php

declare(strict_types=1);

namespace App\Application\UseCases\Product;

use App\Application\DTOs\Product\CreateProductDTO;

final class CreateProductUseCase implements CreateProductUseCaseInterface
{
    public function __construct(
        // private UserRepositoryInterface $repository
    ) {}

    public function execute(CreateProductDTO $dto): mixed
    {
        // TODO: Implement business logic
        return null;
    }
}