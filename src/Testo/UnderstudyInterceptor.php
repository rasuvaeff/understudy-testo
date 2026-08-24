<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\Testo;

use Rasuvaeff\Understudy\Exception\VerificationFailed;
use Rasuvaeff\Understudy\Understudy;
use Testo\Assert\State\Assertion\AssertionException;
use Testo\Assert\State\Assertion\AssertionSuccess;
use Testo\Assert\TestState;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Value\Status;
use Testo\Core\Value\TestType;
use Testo\Pipeline\Attribute\InterceptorOptions;
use Testo\Pipeline\Middleware\TestRunInterceptor;

/**
 * Ends every test with understudy's own bookkeeping done for it.
 *
 * After a body that finished on its own the context is verified: an `expect()`
 * the code under test never made turns the run into a failure. A failing or
 * skipped body keeps its original outcome — verification would only mask it.
 * Either way the context is reset, so one test can never leak a double into
 * the next.
 *
 * The verification itself is counted into the test's `assertions` metric and
 * appended to the collected {@see TestState}, so it is visible to anything
 * reading the result. It is absent from the rendered `assert-history` block:
 * the collector renders that text before returning, and this interceptor is
 * outer to it, so the record simply does not exist yet at rendering time.
 *
 * This interceptor sits just outside Testo's assert collector: that is where
 * the collected {@see TestState} is already attached to the result and can be
 * appended to through its public surface — the collector's static state is
 * internal to `Testo\Assert` and rightly closed to adapters.
 *
 * @api
 */
/*
 * Scoped to plain tests. Inline tests (`#[TestInline]`) and benchmarks are
 * deliberately out: a benchmark runs its body many times, so per-iteration
 * verification would measure understudy rather than the code, and the inline
 * path has never been exercised against this adapter. A double created in an
 * inline test is therefore nobody's to reset — declare doubles in plain tests.
 */
#[InterceptorOptions(
    order: InterceptorOptions::ORDER_ASSERTIONS - 100,
    testType: TestType::Test,
)]
final readonly class UnderstudyInterceptor implements TestRunInterceptor
{
    /**
     * Statuses a body reaches by running to its end. `Risky` and `Flaky` are
     * among them: the first is a passing test Testo found suspicious, the
     * second a passing test that needed retries. Both ran their code, so both
     * owe their expectations an answer — verifying only `Passed` left an
     * unfulfilled `expect()` silently unchecked in exactly the tests that look
     * most like they need checking.
     *
     * @var list<Status>
     */
    private const array COMPLETED = [Status::Passed, Status::Flaky, Status::Risky];

    public function __construct(
        private bool $strictStubs = false,
    ) {}

    /**
     * @param callable(TestInfo): TestResult $next
     */
    #[\Override]
    public function runTest(TestInfo $info, callable $next): TestResult
    {
        try {
            $result = $next($info);

            return \in_array($result->status, self::COMPLETED, strict: true)
                ? $this->verify($result)
                : $result;
        } finally {
            Understudy::reset();
        }
    }

    private function verify(TestResult $result): TestResult
    {
        try {
            Understudy::verifyAll($this->strictStubs);
        } catch (VerificationFailed $failure) {
            return $this->record($result, new AssertionException(
                value: 'understudy',
                assertion: 'expectations verified',
                context: '',
                reason: $failure->getMessage(),
                details: '',
            ))->with(Status::Failed)->withFailure($failure);
        }

        return $this->clearRiskOfNotAsserting($this->record($result, new AssertionSuccess(
            value: 'understudy',
            assertion: 'expectations verified',
            context: '',
        )));
    }

    /**
     * Takes back a `Risky` verdict this adapter is the reason for.
     *
     * Testo calls a passing test risky when it recorded no assertion, and it
     * decides that innermost — before the collected {@see TestState} exists on
     * the result and therefore before we can contribute anything to it. A test
     * whose only check was an understudy expectation looks assertion-free at
     * that moment, and the blame is simply wrong: it did check something, and
     * the check passed.
     *
     * Narrow on purpose. Our record has to be the only one in the history: any
     * other entry means the test asserted on its own and the risk was declared
     * for a reason of its own — a stale `#[ExpectNoAssertions]`, say — which is
     * none of our business to overrule.
     */
    private function clearRiskOfNotAsserting(TestResult $result): TestResult
    {
        $state = $result->getAttribute(TestState::class);

        return $result->status === Status::Risky
            && $state instanceof TestState
            && \count($state->history) === 1
                ? $result->with(Status::Passed)
                : $result;
    }

    /**
     * Accounts the verification as one more assertion of the test.
     *
     * {@see \Testo\Core\Value\Summary::withAddedMetric()} adds to the metric
     * rather than replacing it, and the collector has already counted its own
     * history into it — so the increment here is exactly one. Passing the size
     * of the history instead would report `2N + 1` assertions for a test that
     * made `N`.
     *
     * The history itself exists only while the assert plugin is part of the
     * suite. Without it verification still ran and is still counted; there is
     * merely no history to append to.
     */
    private function record(TestResult $result, AssertionSuccess|AssertionException $record): TestResult
    {
        $state = $result->getAttribute(TestState::class);

        if ($state instanceof TestState) {
            $state->history[] = $record;
        }

        return $result->withSummary($result->summary->withAddedMetric('assertions', 1));
    }
}
