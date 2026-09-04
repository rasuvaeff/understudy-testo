<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\Testo\Tests\Integration\Fixtures\Seams;

use Rasuvaeff\Understudy\Understudy;
use Testo\Assert;
use Testo\Data\DataProvider;
use Testo\Repeat;
use Testo\Test;

use function Rasuvaeff\Understudy\expect;

/**
 * The seams between this adapter and the rest of Testo's plugins. Each test
 * begins by asking whether the runtime it was handed is empty: a plugin that
 * runs a body more than once, or runs several bodies under one pipeline
 * invocation, would leak the previous run's doubles into this one, and
 * `idle()` is the only question that catches it before the leak turns into a
 * confusing verdict somewhere else.
 *
 * @internal
 */
#[Test]
final class SeamsTest
{
    /**
     * Each dataset is a run of its own: one unmet expectation must fail its
     * own dataset and no other.
     */
    #[DataProvider('eachDatasetGetsItsOwnRuntimeProvider')]
    public function eachDatasetGetsItsOwnRuntime(int $code, bool $call): void
    {
        Assert::true(Understudy::idle());

        $gate = Understudy::for(Gate::class);

        expect(static fn() => $gate->open($code));

        if ($call) {
            $gate->open($code);
        }
    }

    /**
     * @return iterable<string, array{int, bool}>
     */
    public static function eachDatasetGetsItsOwnRuntimeProvider(): iterable
    {
        yield 'called' => [1, true];
        yield 'forgotten' => [2, false];
        yield 'called again' => [3, true];
    }

    /**
     * Every repetition is a run of its own too — the same claim, made by the
     * plugin that repeats a body instead of the one that parametrises it.
     */
    #[Repeat(times: 3)]
    public function eachRepetitionGetsItsOwnRuntime(): void
    {
        Assert::true(Understudy::idle());

        $gate = Understudy::for(Gate::class);

        expect(static fn() => $gate->open(4));
        $gate->open(4);
    }

    /**
     * The inline test in {@see Counter} left a double with an unmet
     * expectation behind. Nothing failed for it — inline tests are outside
     * this adapter — and the runtime a plain test is handed is still empty.
     */
    public function anInlineTestLeavesNothingBehind(): void
    {
        Assert::true(Understudy::idle());
    }
}
