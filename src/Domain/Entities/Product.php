<?php

declare(strict_types=1);

namespace App\Domain\Entities;

final class Product
{
    public function __construct(
        public ?int $id,
        public string $name,
        public string $description,
        public float $price,
        public string $sku,
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'sku' => $this->sku,
        ];
    }
}
