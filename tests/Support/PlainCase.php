<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\Testo\Tests\Support;

use Rasuvaeff\Understudy\StrictStubs;

/**
 * A test case with no class-level `#[StrictStubs]`.
 */
class PlainCase
{
    public function plain(): void {}

    #[StrictStubs]
    public function strict(): void {}
}
