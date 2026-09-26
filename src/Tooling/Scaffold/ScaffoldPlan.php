<?php

declare(strict_types=1);

namespace App\Tooling\Scaffold;

final class ScaffoldPlan
{
    /** @var list<ScaffoldAction> */
    private array $actions = [];

    public function add(ScaffoldAction $action): self
    {
        $this->actions[] = $action;

        return $this;
    }

    public function directory(string $relativePath): self
    {
        return $this->add(ScaffoldAction::directory($relativePath));
    }

    public function file(string $relativePath, string $contents): self
    {
        return $this->add(ScaffoldAction::file($relativePath, $contents));
    }

    public function merge(self $other): self
    {
        foreach ($other->actions() as $action) {
            $this->actions[] = $action;
        }

        return $this;
    }

    /**
     * @return list<ScaffoldAction>
     */
    public function actions(): array
    {
        return $this->actions;
    }

    public function isEmpty(): bool
    {
        return $this->actions === [];
    }
}
