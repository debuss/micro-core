<?php

namespace Core\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
readonly class Patch extends Route
{

    public function __construct(string $path)
    {
        parent::__construct('PATCH', $path);
    }
}
