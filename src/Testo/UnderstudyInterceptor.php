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
 * After a passing body the context is verified: an `expect()` the code under
 * test never made turns the pass into a failure. A failing or skipped body
 * keeps its original outcome — verification would only mask it. Either way
 * the context is reset, so one test can never leak a double into the next.
 *
 * The verification itself is recorded as an assertion on the test's
 * {@see TestState}, so it shows up in the assertion count like any other
 * check.
 *
 * This interceptor sits just outside Testo's assert collector: that is where
 * the collected {@see TestState} is already attached to the result and can be
 * appended to through its public surface — the collector's static state is
 * internal to `Testo\Assert` and rightly closed to adapters.
 *
 * @api
 */
#[InterceptorOptions(
    order: InterceptorOptions::ORDER_ASSERTIONS - 100,
    testType: TestType::Test,
)]
final readonly class UnderstudyInterceptor implements TestRunInterceptor
{
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

            return $result->status === Status::Passed
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

        return $this->record($result, new AssertionSuccess(
            value: 'understudy',
            assertion: 'expectations verified',
            context: '',
        ));
    }

    /**
     * Appends the outcome of the verification to the collected assertion
     * history and recounts the metric the collector has already written.
     */
    private function record(TestResult $result, AssertionSuccess|AssertionException $record): TestResult
    {
        $state = $result->getAttribute(TestState::class);

        if (!$state instanceof TestState) {
            // The assert plugin is not part of this suite; verification still
            // ran, there is just no history to account it in.
            return $result;
        }

        $state->history[] = $record;

        return $result->withSummary(
            $result->summary->withAddedMetric('assertions', \count($state->history)),
        );
    }
}
