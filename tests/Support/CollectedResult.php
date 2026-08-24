<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\Testo\Tests\Support;

use Testo\Assert\State\Assertion\AssertionSuccess;
use Testo\Assert\TestState;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Value\Status;

/**
 * Builds a passing {@see TestResult} shaped the way Testo's assert collector
 * hands one over: the state carries the assertions the body made, and the
 * summary already counts them under the `assertions` metric.
 *
 * Anything the adapter adds on top of that is an increment. A fixture that
 * starts from an empty history and a virgin summary cannot tell an increment
 * from a running total, which is how a doubled assertion count once shipped.
 *
 * @api
 */
final readonly class CollectedResult
{
    public static function with(TestInfo $info, int $assertions): TestResult
    {
        $state = new TestState();

        for ($i = 0; $i < $assertions; ++$i) {
            $state->history[] = new AssertionSuccess(value: 'body', assertion: 'same', context: '');
        }

        $result = (new TestResult(info: $info, status: Status::Passed))
            ->withAttribute(TestState::class, $state);

        return $result->withSummary($result->summary->withAddedMetric('assertions', $assertions));
    }
}
