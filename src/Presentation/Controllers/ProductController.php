<?php

declare(strict_types=1);

namespace App\Presentation\Controllers;

use App\Application\UseCases\Product\ListProductsUseCase;
use App\Core\Http\Cache\CacheResponse;
use Erebor\Mithril\Http\HttpContext;
use Erebor\Mithril\Http\Response;

final class ProductController
{
    public function __construct(
        private ListProductsUseCase $listProductsUseCase
    ) {}

    #[CacheResponse(ttl: 30, tags: ['products'])]
    public function index(HttpContext $context): Response
    {
        return Response::json([
            'data' => $this->listProductsUseCase->execute(),
        ]);
    }

    #[CacheResponse(ttl: 30, private: true, vary: ['user'], tags: ['products'])]
    public function secure(HttpContext $context): Response
    {
        return Response::json([
            'data' => $this->listProductsUseCase->execute(),
        ]);
    }
}
