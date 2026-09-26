<?php

declare(strict_types=1);

namespace App\Tooling\Doctor;

use App\Tooling\Project\Project;

final readonly class DoctorContext
{
    public function __construct(
        public Project $project,
        public bool $strict = false,
    ) {}

    public function root(): string
    {
        return $this->project->root();
    }
}
