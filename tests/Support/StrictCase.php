<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\Testo\Tests\Support;

use Rasuvaeff\Understudy\StrictStubs;

#[StrictStubs]
abstract class StrictCase
{
    abstract public function plain(): void;

    #[StrictStubs(false)]
    abstract public function lenient(): void;
}
