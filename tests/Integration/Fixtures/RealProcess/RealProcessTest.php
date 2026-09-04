<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\Testo\Tests\Integration\Fixtures\RealProcess;

use Rasuvaeff\Understudy\Understudy;
use Testo\Test;

use function Rasuvaeff\Understudy\expect;

#[Test]
/**
 * @internal
 */
final class RealProcessTest
{
    public function expectationOnlyTestIsCounted(): void
    {
        $gate = Understudy::for(Gate::class);

        expect(static fn() => $gate->open(1));
        $gate->open(1);
    }

    public function unmetExpectationIsARegularFailure(): void
    {
        $gate = Understudy::for(Gate::class);

        expect(static fn() => $gate->open(2));
    }
}
