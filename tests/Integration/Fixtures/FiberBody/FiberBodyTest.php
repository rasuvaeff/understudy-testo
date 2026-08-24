<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\Testo\Tests\Integration\Fixtures\FiberBody;

use Rasuvaeff\Understudy\Understudy;
use Testo\Assert;
use Testo\Fiber\RunInFiber;
use Testo\Test;

use function Rasuvaeff\Understudy\expect;

/**
 * `#[RunInFiber]` puts the whole per-test pipeline in one Fiber, and the
 * assert collector then opens a second one around the body. The interceptor
 * therefore never stands in the context the body's doubles live in — which is
 * how an unmet expectation used to pass here while the identical test without
 * the attribute failed.
 *
 * @internal
 */
#[Test]
final class FiberBodyTest
{
    #[RunInFiber]
    public function anUnmetExpectationInsideAFiberMustFail(): void
    {
        $gate = Understudy::for(Gate::class);

        expect(static fn() => $gate->open(7));

        Assert::true(actual: true);
    }

    #[RunInFiber]
    public function aSatisfiedExpectationInsideAFiberPasses(): void
    {
        $gate = Understudy::for(Gate::class);

        expect(static fn() => $gate->open(7))->returns(true);
        $gate->open(7);

        Assert::true(actual: true);
    }

    public function theFiberBodyLeftNothingBehind(): void
    {
        // Runs after both, in the main flow: teardown has to have reached the
        // Fiber's context, or these doubles answer this test too.
        Assert::true(Understudy::idle());
    }
}
