<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\Testo\Tests\Integration\Fixtures\ScopeIsolation;

use Rasuvaeff\Understudy\Understudy;
use Testo\Assert;
use Testo\Test;

use function Rasuvaeff\Understudy\expect;

/**
 * A nested `scope()` answers for the context it opened and nothing else.
 *
 * Only this package can put the claim where it broke. The engine's own suite
 * calls `scope()` from a plain function; here the interceptor stands in a
 * teardown position and asks the global `Understudy::verifyAll()` — which is
 * the shape the defect took in practice: a `scope()` reached for inside a test
 * to drop doubles holding OS resources before `#[AfterTest]`, over a test
 * whose own expectations are deliberately still open.
 *
 * Against core 0.4.x the first body here fails; against 0.5.0 it passes.
 *
 * @internal
 */
#[Test]
final class ScopeIsolationTest
{
    public function aScopeDoesNotAnswerForTheTestsOwnPendingClaim(): void
    {
        $outer = Understudy::for(Gate::class);

        // Declared before the scope and satisfied after it: while the scope
        // closes, this test's ledger is violated.
        expect(static fn() => $outer->open(1));

        Understudy::scope(static function (): void {
            $inner = Understudy::for(Gate::class);

            expect(static fn() => $inner->open(2));
            $inner->open(2);
        });

        $outer->open(1);

        Assert::true(actual: true);
    }

    /**
     * The other half: what the scope does own is still judged, and the test
     * fails from inside the scope rather than at teardown.
     */
    public function aScopeStillRefusesItsOwnUnmetClaim(): void
    {
        Understudy::scope(static function (): void {
            $inner = Understudy::for(Gate::class);

            expect(static fn() => $inner->open(3));
        });

        Assert::true(actual: true);
    }

    public function theScopeLeftNothingBehind(): void
    {
        Assert::true(Understudy::idle());
    }
}
