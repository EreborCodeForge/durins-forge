<?php

declare(strict_types=1);

namespace App\Core\Http;

use Closure;
use Erebor\Mithril\Container;
use Erebor\Mithril\Contracts\PipelineContract;
use Erebor\Mithril\Http\HttpContext;
use Erebor\Mithril\Http\Response;
use RuntimeException;

class Pipeline implements PipelineContract
{
    private HttpContext $passable;
    private array $pipes = [];

    public function __construct(private Container $container) {}

    public function send(HttpContext $passable): static
    {
        $this->passable = $passable;
        return $this;
    }

    public function through(array $pipes): static
    {
        $this->pipes = $pipes;
        return $this;
    }

    public function then(callable $destination): Response
    {
        $pipeline = array_reduce(
            array_reverse($this->pipes),
            $this->carry(),
            $this->prepareDestination($destination)
        );

        return $pipeline($this->passable);
    }

    private function prepareDestination(callable $destination): Closure
    {
        return function (HttpContext $passable) use ($destination) {
            return $destination($passable);
        };
    }

    private function carry(): Closure
    {
        return function (callable $stack, $pipe) {
            return function (HttpContext $passable) use ($stack, $pipe) {
                if (is_string($pipe)) {
                    $pipeInstance = $this->container->resolve($pipe);
                } else {
                    $pipeInstance = $pipe;
                }

                if (!method_exists($pipeInstance, 'handle')) {
                    throw new RuntimeException("Middleware must implement handle method");
                }

                return $pipeInstance->handle($passable, $stack);
            };
        };
    }
}
