<?php

declare(strict_types=1);

namespace App\Presentation\Controllers;

use App\Application\UseCases\Product\ListProductsUseCase;
use Erebor\Mithril\Http\HttpContext;
use Erebor\Mithril\Http\Response;

final class ProductController
{
    public function __construct(
        private ListProductsUseCase $listProductsUseCase
    ) {}

    public function index(HttpContext $context): Response
    {
        return (new Response())->json([
            'data' => $this->listProductsUseCase->execute(),
        ]);
    }
}
