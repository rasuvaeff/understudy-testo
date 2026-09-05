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
 * After a plain test that finished on its own the context is verified: an
 * `expect()` the code under test never made turns the run into a failure. A
 * failing or skipped body keeps its original outcome — verification would
 * only mask it. Whatever the kind of test and whatever its outcome, the
 * context is reset, so nothing can leak a double into the next test.
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
 * Verification is for plain tests only: a benchmark runs its body many times,
 * so verifying it would measure understudy rather than the code, and an
 * inline test is meant to be a pure table-driven check with no setup to
 * verify. Reset is for every kind of test, which is why this interceptor is
 * not filtered by type. A double built inside an inline test used to survive
 * into the next plain test's body — found by running one: the first dataset
 * of the next test was handed a runtime that was not empty.
 */
#[InterceptorOptions(order: InterceptorOptions::ORDER_ASSERTIONS - 100)]
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

            return $info->identity->type === TestType::Test->value
                && \in_array($result->status, self::COMPLETED, strict: true)
                    ? $this->verify($result)
                    : $result;
        } finally {
            Understudy::reset();
        }
    }

    private function verify(TestResult $result): TestResult
    {
        // A test that created no double asked understudy nothing, and there is
        // nothing here to report about it. Recording a success anyway added an
        // assertion to every test in the suite and, through
        // clearRiskOfNotAsserting(), took back the runner's "this test made no
        // assertion" verdict for tests that never touched this adapter —
        // silently disabling a check of the runner across the whole suite in
        // exchange for installing an adapter.
        if (Understudy::idle()) {
            return $result;
        }

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
     * Narrow on purpose, twice over. Our record has to be the only one in the
     * history: any other entry means the test asserted on its own and the risk
     * was declared for a reason of its own — a stale `#[ExpectNoAssertions]`,
     * say — which is none of our business to overrule. And the caller only
     * reaches here for a test that actually held doubles: without that guard
     * the condition held for every test in the suite, including ones written
     * before this adapter existed.
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
