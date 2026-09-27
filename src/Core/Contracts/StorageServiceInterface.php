<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Core\Contracts;

use Erebor\Mithril\Http\UploadedFile;

interface StorageServiceInterface
{
    public function store(UploadedFile $file, string $path): string;
    public function delete(string $path): void;
    public function getUrl(string $path): string;
}
