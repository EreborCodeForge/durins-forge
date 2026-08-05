<?php

declare(strict_types=1);

namespace App\Core\Routing;

use Erebor\Mithril\Container;
use Erebor\Mithril\Http\HttpContext;
use Erebor\Mithril\Http\Response;
use Erebor\Mithril\Routing\Contracts\HandlerResolver;

final class ControllerHandlerResolver implements HandlerResolver
{
    public function __construct(
        private readonly Container $container
    ) {}

    public function resolve(mixed $handler): callable
    {
        if (is_callable($handler)) {
            return $handler;
        }

        if (is_array($handler) && count($handler) === 2) {
            [$controller, $action] = $handler;

            return function (HttpContext $req, array $params) use ($controller, $action): Response {
                $instance = $this->container->get($controller);

                if (!method_exists($instance, $action)) {
                    throw new \RuntimeException("Action [$action] not found in controller [$controller]");
                }

                if (isset($params['path'])) {
                    return $instance->$action($req, $params['path']);
                }

                return $instance->$action($req, $params);
            };
        }

        throw new \RuntimeException('Invalid handler type for resolver.');
    }
}
