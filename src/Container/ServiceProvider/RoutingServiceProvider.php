<?php

namespace Core\Container\ServiceProvider;

use Core\Strategy\ProblemDetailsStrategy;
use Closure;
use League\Container\ServiceProvider\AbstractServiceProvider;
use League\Route\Router;
use Psr\Container\ContainerInterface;

class RoutingServiceProvider extends AbstractServiceProvider
{

    public function __construct(
        private readonly Closure $route_loader
    ) {}

    public function provides(string $id): bool
    {
        return $id === Router::class;
    }

    public function register(): void
    {
        $this
            ->getContainer()
            ->add(Router::class, function (ContainerInterface $container) {
                $router = new Router();
                $router->setStrategy(
                    $container->get(ProblemDetailsStrategy::class)->setContainer($container)
                );

                ($this->route_loader)($router, $container);

                return $router;
            })->addArgument($this->getContainer());
    }
}
