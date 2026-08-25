<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\Testo\Tests\Integration\Fixtures\Statuses;

use Rasuvaeff\Understudy\Understudy;
use Testo\Assert;
use Testo\Assert\ExpectNoAssertions;
use Testo\Core\Exception\SkipTest;
use Testo\Retry;
use Testo\Test;

use function Rasuvaeff\Understudy\expect;

/**
 * Every status a Testo body can reach on its own, produced by the runner
 * rather than constructed: the unit suite builds `TestResult`s by hand, and a
 * hand-built `Risky` is a belief about the runner, not an observation of it.
 *
 * Test order is declaration order, and two of these tests read what the ones
 * before them left behind.
 *
 * @internal
 */
#[Test]
final class StatusesTest
{
    private static int $attempts = 0;

    /**
     * A skipped body keeps its skip. Verification would only replace a
     * decision the test made about itself with one about a call it never got
     * the chance to make.
     */
    public function aSkippedBodyKeepsItsSkip(): never
    {
        $gate = Understudy::for(Gate::class);

        expect(static fn() => $gate->open(1));

        throw new SkipTest('the body decided not to run');
    }

    /**
     * An error keeps its own cause. The unmet expectation here is a
     * consequence of the throw, and reporting it would hide the throw.
     */
    public function anErroringBodyKeepsItsError(): never
    {
        $gate = Understudy::for(Gate::class);

        expect(static fn() => $gate->open(2));

        throw new \RuntimeException('the original failure');
    }

    /**
     * What the two tests above left behind: nothing. Reset runs in `finally`,
     * so a body that skipped and a body that threw both hand the next test a
     * clean runtime.
     */
    public function neitherLeftADoubleBehind(): void
    {
        Assert::true(Understudy::idle());
    }

    /**
     * A retried body that passes on the second attempt is `Flaky`, and a
     * flaky test owes its expectations the same answer a passing one does.
     * The double is created per attempt, which is the point: the first
     * attempt's runtime is gone before the second one starts.
     */
    #[Retry(maxAttempts: 2)]
    public function aFlakyBodyIsVerifiedOnTheAttemptThatPasses(): void
    {
        $gate = Understudy::for(Gate::class);

        expect(static fn() => $gate->open(3));
        $gate->open(3);

        ++self::$attempts;

        Assert::same(self::$attempts > 1, expected: true);
    }

    /**
     * The other half: verification is a real verdict, so a body that keeps
     * failing it keeps being retried and ends `Failed` — the retry policy
     * sees our failure exactly as it sees an assertion's.
     */
    #[Retry(maxAttempts: 2)]
    public function aRetriedUnmetExpectationStillFails(): void
    {
        $gate = Understudy::for(Gate::class);

        expect(static fn() => $gate->open(4));

        Assert::true(actual: true);
    }

    /**
     * A risk declared for a reason of its own is none of our business. This
     * test asserts and says it will not, which is Testo's contradiction, not
     * ours — the verification record must not talk it out of the verdict.
     */
    #[ExpectNoAssertions]
    public function aRiskDeclaredByTestoIsNotClearedByUs(): void
    {
        $gate = Understudy::for(Gate::class);

        expect(static fn() => $gate->open(5));
        $gate->open(5);

        Assert::true(actual: true);
    }
}
