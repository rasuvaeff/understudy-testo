<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\Testo\Tests\Integration\Fixtures\Seams;

use Rasuvaeff\Understudy\Understudy;
use Testo\Inline\TestInline;

/**
 * Production code carrying an inline test, which is what `#[TestInline]` is
 * for. The body builds a double and leaves its expectation unmet: the
 * adapter is scoped to plain tests, so nothing here is verified — and the
 * question this fixture answers is what happens to the double afterwards.
 *
 * @internal
 */
final class Counter
{
    #[TestInline(arguments: [2], result: 3)]
    public function next(int $value): int
    {
        $gate = Understudy::for(Gate::class);

        Understudy::expect(static fn() => $gate->open(99));

        return $value + 1;
    }
}
