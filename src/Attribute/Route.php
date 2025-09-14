<?php

namespace Core\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS|Attribute::TARGET_METHOD)]
readonly class Route
{
    public function __construct(
        public string|array $methods = [],
        public string $path = ''
    ) {}
}
