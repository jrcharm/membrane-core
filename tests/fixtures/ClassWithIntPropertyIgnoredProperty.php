<?php

declare(strict_types=1);

namespace Membrane\Fixtures;

use Membrane\Attribute\Ignored;

class ClassWithIntPropertyIgnoredProperty
{
    public int $integerProperty;
    #[Ignored]
    public string $ignoredProperty;
}
