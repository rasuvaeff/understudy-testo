<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\Testo\Tests\Support;

use Rasuvaeff\Understudy\StrictStubs;

#[StrictStubs(false)]
abstract class LenientCase
{
    abstract public function plain(): void;

    #[StrictStubs]
    abstract public function strict(): void;
}
