<?php

declare(strict_types=1);

namespace App\Core\Attributes;

use Attribute;

/**
 * Marca uma classe como Service Provider para discovery.
 * Usado pelo container:compile para encontrar providers (via scan) ou validar.
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly final class Discoverable
{
    public function __construct(
        public readonly ?string $tag = 'default',
    ) {}
}
