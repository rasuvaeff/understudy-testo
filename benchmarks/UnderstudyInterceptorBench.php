<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\Testo\Benchmarks;

use function Rasuvaeff\Understudy\expect;
use function Rasuvaeff\Understudy\when;

use Internal\Path;
use Rasuvaeff\Understudy\Testo\UnderstudyInterceptor;
use Rasuvaeff\Understudy\Understudy;
use Testo\Assert\TestState;
use Testo\Bench;
use Testo\Core\Context\CaseInfo;
use Testo\Core\Context\Identity\SuiteIdentity;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Definition\CaseDefinition;
use Testo\Core\Definition\TestDefinition;
use Testo\Core\Value\Status;

/**
 * What the adapter charges for ending a test automatically.
 *
 * Every scenario is measured against what the code would have to do by hand:
 * a bare pipeline for the wrapper itself, and a manual `reset()` for the
 * lifecycle around real doubles — because a suite that skips verification
 * still cannot let one test's doubles leak into the next.
 */
final class UnderstudyInterceptorBench
{
    private static ?TestInfo $info = null;

    private static UnderstudyInterceptor $interceptor;

    // --- Wrapping a plain passing test --------------------------------------

    #[Bench(['bare pipeline' => [self::class, 'plainPipeline']], calls: 10_000)]
    public static function passWithoutDoubles(): void
    {
        self::interceptor()->runTest(self::info(), self::passing(...));
    }

    public static function plainPipeline(): void
    {
        self::passing(self::info());
    }

    // --- Full lifecycle around a real double --------------------------------

    #[Bench(['reset only' => [self::class, 'fulfilledWithManualReset']], calls: 10_000)]
    public static function passWithFulfilledExpectation(): void
    {
        self::interceptor()->runTest(self::info(), self::fulfilled(...));
    }

    public static function fulfilledWithManualReset(): void
    {
        self::fulfilledBody();

        Understudy::reset();
    }

    // --- Verification that fails after a passing body -----------------------

    /**
     * The failure path renders the unmet-expectation report; it is priced
     * separately because a suite pays it exactly once — the moment a test
     * goes red for a reason only verification could have caught.
     */
    #[Bench(['reset only' => [self::class, 'unmetWithManualReset']], calls: 1_000)]
    public static function unmetExpectationFailsAfterTheFact(): void
    {
        self::interceptor()->runTest(self::info(), self::unmet(...));
    }

    public static function unmetWithManualReset(): void
    {
        self::unmetBody();

        Understudy::reset();
    }

    private static function interceptor(): UnderstudyInterceptor
    {
        return self::$interceptor ??= new UnderstudyInterceptor();
    }

    private static function info(): TestInfo
    {
        return self::$info ??= new TestInfo(
            name: 'bench',
            caseInfo: new CaseInfo(
                definition: new CaseDefinition(name: 'Bench', type: 'test', file: Path::create(__FILE__)),
                suiteIdentity: new SuiteIdentity('Benchmarks'),
            ),
            testDefinition: new TestDefinition(
                reflection: new \ReflectionMethod(__CLASS__, 'plainPipeline'),
            ),
        );
    }

    private static function passing(TestInfo $info): TestResult
    {
        return (new TestResult(info: $info, status: Status::Passed))
            ->withAttribute(TestState::class, new TestState());
    }

    private static function fulfilled(TestInfo $info): TestResult
    {
        self::fulfilledBody();

        return self::passing($info);
    }

    private static function fulfilledBody(): void
    {
        $double = Understudy::for(BenchContract::class);

        when(static fn () => $double->get())->returns(7);

        $double->get();
    }

    private static function unmet(TestInfo $info): TestResult
    {
        self::unmetBody();

        return self::passing($info);
    }

    private static function unmetBody(): void
    {
        $double = Understudy::for(BenchContract::class);

        expect(static fn () => $double->get());
    }
}

interface BenchContract
{
    public function get(): int;
}
