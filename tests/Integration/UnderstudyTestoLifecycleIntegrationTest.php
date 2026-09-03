<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\Testo\Tests\Integration;

use Testo\Assert;
use Testo\Codecov\CoversNothing;
use Testo\Test;

/**
 * Runs a child Testo process with the real plugin pipeline and checks the
 * runner's rendered summary, rather than constructing TestResult by hand.
 */
#[Test]
#[CoversNothing]
final class UnderstudyTestoLifecycleIntegrationTest
{
    public function realRunnerCountsVerificationAndReportsOneFailure(): void
    {
        [$exit, $output] = $this->runFixture();

        Assert::same($exit, 1);
        Assert::string($output)
            ->contains('Total   2 tests · 2 assertions')
            ->contains('1 passed, 1 failed');
        Assert::false(str_contains($output, 'Risky: 1'));
        Assert::string($output)->contains('never');
    }

    public function aFiberBodysExpectationsAreVerifiedToo(): void
    {
        [$exit, $output] = $this->runFixture('FiberBody');

        // One of the three fails, and it is the unmet one. The other two
        // carry the rest of the claim: a satisfied expectation inside a Fiber
        // still passes, and the context was dropped before the next test.
        Assert::same($exit, 1);
        Assert::string($output)
            // Three bodies asserting once each, plus one verification apiece.
            ->contains('Total   3 tests · 6 assertions')
            ->contains('2 passed, 1 failed')
            ->contains('anUnmetExpectationInsideAFiberMustFail')
            ->contains('never');
    }

    /**
     * A nested scope answers for its own context, under a real runner.
     *
     * The engine's suite calls `scope()` from a plain function. Here the
     * interceptor stands where it actually stands — a teardown position asking
     * the global `Understudy::verifyAll()` — which is the shape the defect took
     * in practice: a scope reached for inside a test to drop doubles holding OS
     * resources, over a test whose own expectations are still open. Against
     * core 0.4.x the first body of the fixture fails; against 0.5.0 it passes.
     */
    public function aNestedScopeDoesNotAnswerForTheTestsOwnPendingClaim(): void
    {
        [$exit, $output] = $this->runFixture('ScopeIsolation');

        // Three bodies: the isolation claim passes, the idle check after it
        // passes, and the one that leaves its OWN claim unmet errors out of
        // the scope — the failure surfaces through the runner rather than
        // being swallowed, and it names the inner call, not the outer one.
        Assert::same($exit, 1);
        Assert::string($output)
            ->contains('Total   3 tests · 4 assertions')
            ->contains('2 passed, 1 error')
            ->contains('aScopeDoesNotAnswerForTheTestsOwnPendingClaim')
            ->contains('expected `open(3)` to be called exactly 1 time');

        // `open(1)` is the enclosing claim, open while the scope closed and
        // satisfied afterwards. A report about it would be the regression.
        Assert::false(str_contains($output, 'open(1)'));
    }

    /**
     * Every status a body reaches on its own, in one run: the plan asks for
     * the matrix to be produced by the runner rather than constructed,
     * because a hand-built `Risky` is a belief about Testo and this is an
     * observation of it.
     */
    public function everyStatusTheRunnerProducesIsHandledAsDocumented(): void
    {
        [$exit, $output] = $this->runFixture('Statuses');

        Assert::same($exit, 1);
        Assert::string($output)
            ->contains('Total   6 tests · 11 assertions')
            ->contains('1 passed, 1 failed, 1 error, 1 skipped, 1 risky, 1 flaky');

        // The error keeps its own cause, and the unmet `open(2)` that the
        // throw left behind is not reported over it.
        Assert::string($output)->contains('RuntimeException: the original failure');
        Assert::false(str_contains($output, 'open(2)'));

        // The skipped body's expectation is not reported either.
        Assert::false(str_contains($output, 'open(1)'));

        // Verification is a verdict the retry policy acts on, exactly as it
        // acts on an assertion's.
        Assert::string($output)->contains(
            'Attempt 1 failed: Understudy `Gate` expected `open(4)` to be called exactly 1 time',
        );
    }

    /**
     * The seams with the plugins that run a body more than once, or several
     * bodies under one pipeline invocation. Each body asks whether the
     * runtime it was handed is empty, which is the only question that catches
     * a leak before it turns into a confusing verdict somewhere else.
     */
    public function everyPluginSeamHandsTheBodyAnEmptyRuntime(): void
    {
        [$exit, $output] = $this->runFixture('Seams');

        Assert::same($exit, 1);
        Assert::string($output)
            // Three datasets, three repetitions counted as one test, one
            // inline test and one plain test.
            ->contains('Total   6 tests · 15 assertions')
            ->contains('5 passed, 1 failed');

        // The one failure is the dataset that forgot its call, and nothing
        // spills onto the dataset after it.
        Assert::string($output)
            ->contains('Dataset #1 [forgotten]')
            ->contains('expected `open(2)` to be called exactly 1 time');
        Assert::false(str_contains($output, 'open(1)'));
        Assert::false(str_contains($output, 'open(3)'));

        // The inline test's own double is never verified — an unmet
        // `open(99)` there is not a failure — and it does not survive into
        // the plain tests either.
        Assert::false(str_contains($output, 'open(99)'));
    }

    /**
     * Filtering changes which tests run, not what happens around them.
     */
    public function aFilteredRunStillVerifies(): void
    {
        [$exit, $output] = $this->runFixture('Seams', '--filter=eachDatasetGetsItsOwnRuntime');

        Assert::same($exit, 1);
        Assert::string($output)
            ->contains('Total   3 tests · 6 assertions')
            ->contains('2 passed, 1 failed')
            ->contains('expected `open(2)` to be called exactly 1 time');
    }

    /**
     * @return array{int, string}
     */
    private function runFixture(string $fixture = 'RealProcess', string ...$arguments): array
    {
        $root = dirname(__DIR__, 2);
        $config = __DIR__ . '/Fixtures/' . $fixture . '/testo.php';

        $command = sprintf(
            '%s %s --config=%s --no-ansi %s 2>&1',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($root . '/vendor/bin/testo'),
            escapeshellarg($config),
            implode(' ', array_map(escapeshellarg(...), $arguments)),
        );

        exec($command, $lines, $exit);

        $output = preg_replace('/\e\[[\d;]*m/', '', implode("\n", $lines));

        return [$exit, $output ?? ''];
    }
}
