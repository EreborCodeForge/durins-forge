<?php

declare(strict_types=1);

namespace App\Tooling\Generators;

use App\Tooling\Scaffold\ScaffoldWriteResult;
use App\Tooling\Scaffold\ScaffoldWriter;

/**
 * Applies a CodeGenerator plan through ScaffoldWriter (conflict-safe by default).
 */
final class GeneratorRunner
{
    public function __construct(
        private readonly ScaffoldWriter $writer = new ScaffoldWriter(),
    ) {}

    public function run(CodeGenerator $generator, GeneratorRequest $request): ScaffoldWriteResult
    {
        $plan = $generator->plan($request);

        return $this->writer->write($request->projectRoot, $plan);
    }
}
