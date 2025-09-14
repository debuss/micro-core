<?php

namespace Core\Container\ServiceProvider;

use Borsch\Config\{Aggregator, Config};
use League\Container\ServiceProvider\AbstractServiceProvider;

class ConfigurationServiceProvider extends AbstractServiceProvider
{

    public function __construct(
        private readonly array $configs = [],
        private readonly string $cache_path = '',
        private readonly bool $use_cache = false
    ) {}

    public function provides(string $id): bool
    {
        return $id == Config::class;
    }

    public function register(): void
    {
        $this->getContainer()
            ->add(Config::class, function () {
                $aggregator = new Aggregator(
                    $this->configs,
                    $this->cache_path,
                    $this->use_cache
                );

                return $aggregator->getMergedConfig();
            });
    }
}
