<?php

namespace Core\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class BasePath extends Route
{

    public function __construct(string $path = '')
    {
        parent::__construct([], $path);
    }
}
