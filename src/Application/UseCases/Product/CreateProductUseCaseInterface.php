<?php

declare(strict_types=1);

namespace App\Application\UseCases\Product;

use App\Application\DTOs\Product\CreateProductDTO;

interface CreateProductUseCaseInterface
{
    public function execute(CreateProductDTO $dto): mixed;
}