<?php

namespace Core\Container\ServiceProvider;

use League\Container\ServiceProvider\{AbstractServiceProvider, BootableServiceProviderInterface};
use Closure;
use Monolog\Logger;
use Psr\Log\{LoggerAwareInterface, LoggerInterface};

class LoggingServiceProvider extends AbstractServiceProvider implements BootableServiceProviderInterface
{

    public function __construct(
        private readonly Closure $logger_factory
    ) {}

    public function boot(): void
    {
        $this
            ->getContainer()
            ->inflector(
                LoggerAwareInterface::class,
                fn(LoggerAwareInterface $class) => $class->setLogger(
                    $this->getContainer()->get(Logger::class)->withName(get_class($class))
                ));
    }

    public function provides(string $id): bool
    {
        return in_array($id, [
            Logger::class,
            LoggerInterface::class
        ]);
    }

    public function register(): void
    {
        $this
            ->getContainer()
            ->add(Logger::class, fn() => ($this->logger_factory)($this->getContainer()));

        $this
            ->getContainer()
            ->add(LoggerInterface::class, fn(): LoggerInterface => $this->getContainer()->get(Logger::class));
    }
}
