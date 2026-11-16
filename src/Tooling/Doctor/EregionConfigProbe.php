<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Doctor;

interface EregionConfigProbe
{
    public function probe(string $binary, string $configPath): EregionConfigReport;
}
