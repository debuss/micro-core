<?php

namespace Core\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
readonly class Delete extends Route
{

    public function __construct(string $path)
    {
        parent::__construct('DELETE', $path);
    }
}
