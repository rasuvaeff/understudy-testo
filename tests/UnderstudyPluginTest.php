<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\Testo\Tests;

use Internal\Container\ObjectContainer;
use Internal\Path;
use Rasuvaeff\Understudy\Exception\VerificationFailed;
use Rasuvaeff\Understudy\Testo\Tests\Support\CollectedResult;
use Rasuvaeff\Understudy\Testo\Tests\Support\CollectingCollector;
use Rasuvaeff\Understudy\Testo\Tests\Support\CollectorContract;
use Rasuvaeff\Understudy\Testo\UnderstudyInterceptor;
use Rasuvaeff\Understudy\Testo\UnderstudyPlugin;
use Rasuvaeff\Understudy\Understudy;
use Testo\Assert;
use Testo\Assert\TestState;
use Testo\Codecov\Covers;
use Testo\Core\Context\CaseInfo;
use Testo\Core\Context\Identity\SuiteIdentity;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Definition\CaseDefinition;
use Testo\Core\Definition\TestDefinition;
use Testo\Core\Value\Status;
use Testo\Core\Value\TestType;
use Testo\Data\DataProvider;
use Testo\Lifecycle\BeforeTest;
use Testo\Pipeline\InterceptorCollector;
use Testo\Test;

use function Rasuvaeff\Understudy\expect;
use function Rasuvaeff\Understudy\when;

#[Test]
#[Covers(UnderstudyPlugin::class)]
#[Covers(UnderstudyInterceptor::class)]
final class UnderstudyPluginTest
{
    #[BeforeTest]
    public function cleanContext(): void
    {
        Understudy::reset();
    }

    public function registersTheInterceptorInTheCollector(): void
    {
        $collector = new CollectingCollector();
        $container = new ObjectContainer();
        $container->set($collector, InterceptorCollector::class);

        (new UnderstudyPlugin())->configure($container);

        Assert::same(\count($collector->interceptors), 1);
        Assert::instanceOf($collector->interceptors[0], UnderstudyInterceptor::class);
    }

    public function satisfiedExpectationKeepsTheTestPassing(): void
    {
        $interceptor = new UnderstudyInterceptor();
        $next = static function (TestInfo $info): TestResult {
            $double = Understudy::for(CollectorContract::class);

            when(static fn() => $double->get())->returns(7);

            $double->get();

            return CollectedResult::with($info, assertions: 3);
        };

        $result = $interceptor->runTest($this->info(), $next);

        Assert::same($result->status, Status::Passed);
        Assert::null($result->failure);

        $state = $result->getAttribute(TestState::class);
        Assert::instanceOf($state, TestState::class);
        Assert::same(\count($state?->history ?? []), 4);
        Assert::true($state?->history[3]->isSuccess());
        // Three assertions of the body plus this one — the metric is an
        // increment, not the running total the collector already counted.
        Assert::same($result->summary->metric('assertions'), 4);
    }

    public function unmetExpectationFailsAPassingTest(): void
    {
        $interceptor = new UnderstudyInterceptor();
        $next = static function (TestInfo $info): TestResult {
            $double = Understudy::for(CollectorContract::class);

            expect(static fn() => $double->get());

            return CollectedResult::with($info, assertions: 3);
        };

        $result = $interceptor->runTest($this->info(), $next);

        Assert::same($result->status, Status::Failed);
        Assert::instanceOf($result->failure, VerificationFailed::class);
        Assert::string($result->failure?->getMessage())
            ->contains('expected')
            ->contains('get(')
            ->contains('never');

        $state = $result->getAttribute(TestState::class);
        Assert::instanceOf($state, TestState::class);
        Assert::same(\count($state?->history ?? []), 4);
        Assert::false($state?->history[3]->isSuccess());
        Assert::same($result->summary->metric('assertions'), 4);
    }

    public function failingBodyKeepsItsOriginalFailure(): void
    {
        $interceptor = new UnderstudyInterceptor();
        $original = new \RuntimeException('the code under test broke');
        $next = static function (TestInfo $info) use ($original): TestResult {
            $double = Understudy::for(CollectorContract::class);

            expect(static fn() => $double->get());

            return new TestResult(info: $info, status: Status::Failed, failure: $original);
        };

        $result = $interceptor->runTest($this->info(), $next);

        Assert::same($result->status, Status::Failed);
        Assert::same($result->failure, $original);
    }

    public function skippedBodyIsNotVerified(): void
    {
        $interceptor = new UnderstudyInterceptor();
        $next = static function (TestInfo $info): TestResult {
            $double = Understudy::for(CollectorContract::class);

            expect(static fn() => $double->get());

            return new TestResult(info: $info, status: Status::Skipped);
        };

        $result = $interceptor->runTest($this->info(), $next);

        Assert::same($result->status, Status::Skipped);
        Assert::null($result->failure);
    }

    /**
     * A test that created no double is none of this adapter's business.
     *
     * The verification recorded a success for every completed test and added
     * an assertion to it, which made `clearRiskOfNotAsserting()` fire for
     * tests that never touched understudy — so installing the adapter for one
     * clear place of verification silently took the runner's "this test made
     * no assertion" check away from the whole suite, tests written before the
     * adapter included.
     */
    public function aTestWithoutDoublesKeepsItsRiskyVerdictAndItsAssertionCount(): void
    {
        $interceptor = new UnderstudyInterceptor();
        $next = static fn(TestInfo $info): TestResult => CollectedResult::with($info, assertions: 0)
            ->with(Status::Risky);

        $result = $interceptor->runTest($this->info(), $next);

        Assert::same($result->status, Status::Risky);
        Assert::same($result->summary->metric('assertions'), 0);
    }

    public function aPassingTestWithoutDoublesGainsNoAssertion(): void
    {
        $interceptor = new UnderstudyInterceptor();
        $next = static fn(TestInfo $info): TestResult => CollectedResult::with($info, assertions: 3);

        $result = $interceptor->runTest($this->info(), $next);

        Assert::same($result->status, Status::Passed);
        Assert::same($result->summary->metric('assertions'), 3);
    }

    public function riskyBodyIsStillVerified(): void
    {
        // Testo calls a passing test risky when it recorded no assertion —
        // which is exactly what a test whose only check is an expectation
        // looks like. Skipping verification here let unmet expectations pass
        // silently in the very tests that lean on them most.
        $interceptor = new UnderstudyInterceptor();
        $next = static function (TestInfo $info): TestResult {
            $double = Understudy::for(CollectorContract::class);

            expect(static fn() => $double->get());

            return new TestResult(info: $info, status: Status::Risky);
        };

        $result = $interceptor->runTest($this->info(), $next);

        Assert::same($result->status, Status::Failed);
        Assert::instanceOf($result->failure, VerificationFailed::class);
    }

    public function flakyBodyIsStillVerified(): void
    {
        // A test that passed after retries ran its code just as much.
        $interceptor = new UnderstudyInterceptor();
        $next = static function (TestInfo $info): TestResult {
            $double = Understudy::for(CollectorContract::class);

            expect(static fn() => $double->get());

            return new TestResult(info: $info, status: Status::Flaky);
        };

        $result = $interceptor->runTest($this->info(), $next);

        Assert::same($result->status, Status::Failed);
    }

    public function flakyIsNeverPromotedToPassed(): void
    {
        // Only the `Risky` verdict is ours to take back. A test that needed
        // retries stays flaky no matter how its expectations went — hiding
        // that would misreport the suite.
        $interceptor = new UnderstudyInterceptor();
        $next = static function (TestInfo $info): TestResult {
            $double = Understudy::for(CollectorContract::class);

            expect(static fn() => $double->get());
            $double->get();

            return CollectedResult::with($info, assertions: 0)->with(Status::Flaky);
        };

        $result = $interceptor->runTest($this->info(), $next);

        Assert::same($result->status, Status::Flaky);
        Assert::same($result->summary->metric('assertions'), 1);
    }

    public function soleVerificationTakesBackTheRiskyVerdict(): void
    {
        $interceptor = new UnderstudyInterceptor();
        $next = static function (TestInfo $info): TestResult {
            $double = Understudy::for(CollectorContract::class);

            expect(static fn() => $double->get());
            $double->get();

            return CollectedResult::with($info, assertions: 0)->with(Status::Risky);
        };

        $result = $interceptor->runTest($this->info(), $next);

        // The test did assert — through understudy — so "no assertions" was
        // never true of it.
        Assert::same($result->status, Status::Passed);
        Assert::same($result->summary->metric('assertions'), 1);
    }

    public function riskyVerdictSurvivesWhenTheTestAssertedOnItsOwn(): void
    {
        // Here the risk was declared for some reason of the test's own, and
        // overruling it is none of this adapter's business.
        $interceptor = new UnderstudyInterceptor();
        $next = static function (TestInfo $info): TestResult {
            $double = Understudy::for(CollectorContract::class);

            expect(static fn() => $double->get());
            $double->get();

            return CollectedResult::with($info, assertions: 3)->with(Status::Risky);
        };

        $result = $interceptor->runTest($this->info(), $next);

        Assert::same($result->status, Status::Risky);
    }

    public function contextIsDroppedAfterEveryTest(): void
    {
        $interceptor = new UnderstudyInterceptor();

        // The first test leaves an expectation behind that nothing fulfils,
        // but its body failed on its own — so verification never ran.
        $leaving = static function (TestInfo $info): TestResult {
            $double = Understudy::for(CollectorContract::class);

            expect(static fn() => $double->get());

            return new TestResult(
                info: $info,
                status: Status::Failed,
                failure: new \RuntimeException('body failed first'),
            );
        };
        $first = $interceptor->runTest($this->info(), $leaving);

        // The next one is plain: had the previous context survived, its unmet
        // expectation would fail this run too.
        $plain = static fn(TestInfo $info): TestResult => new TestResult(info: $info, status: Status::Passed);
        $second = $interceptor->runTest($this->info(), $plain);

        Assert::same($first->status, Status::Failed);
        Assert::same($second->status, Status::Passed);
    }

    /**
     * Verification is scoped to plain tests; the reset is not.
     *
     * A benchmark would pay verification per iteration, and an inline case
     * has no setup to answer for — so neither is verified. Both still drop
     * their doubles, because a body that skips verification must not leak
     * into the one after it. That half is the one that actually broke once:
     * the scope used to be an `InterceptorOptions(testType:)` filter, which
     * skipped the reset too, and a `#[TestInline]` double survived into the
     * next plain test.
     *
     * `$info->identity->type` is where the decision is read, so this is the
     * test that reads it — a real `#[Bench]` fixture would be measuring
     * Testo's benchmark runner instead.
     */
    #[DataProvider('unverifiedTypeProvider')]
    public function aTypeThatIsNotAPlainTestIsResetButNotVerified(string $type): void
    {
        $interceptor = new UnderstudyInterceptor();

        $leaving = static function (TestInfo $info): TestResult {
            $double = Understudy::for(CollectorContract::class);

            expect(static fn() => $double->get());

            return new TestResult(info: $info, status: Status::Passed);
        };

        // Not verified: the unmet expectation is not this body's problem.
        $unverified = $interceptor->runTest($this->info($type), $leaving);

        Assert::same($unverified->status, Status::Passed);

        // Reset anyway: had the context survived, the plain test after it
        // would inherit an expectation nothing fulfils and fail for it.
        $plain = static fn(TestInfo $info): TestResult => new TestResult(info: $info, status: Status::Passed);
        $next = $interceptor->runTest($this->info(), $plain);

        Assert::same($next->status, Status::Passed);
    }

    /**
     * Every type Testo has that is not a plain test, by value rather than by
     * name — the interceptor compares `identity->type` against
     * `TestType::Test->value`, so a type added to the enum lands here.
     *
     * @return iterable<string, array{string}>
     */
    public static function unverifiedTypeProvider(): iterable
    {
        foreach (TestType::cases() as $type) {
            if ($type !== TestType::Test) {
                yield $type->name => [$type->value];
            }
        }
    }

    public function strictStubsFlagReachesTheVerification(): void
    {
        $interceptor = new UnderstudyInterceptor(strictStubs: true);
        $next = static function (TestInfo $info): TestResult {
            $double = Understudy::for(CollectorContract::class);

            when(static fn() => $double->get())->returns(7);

            return new TestResult(info: $info, status: Status::Passed);
        };

        $result = $interceptor->runTest($this->info(), $next);

        Assert::same($result->status, Status::Failed);
        Assert::string((string) $result->failure?->getMessage())->contains('never used');
    }

    public function unusedStubIsToleratedByDefault(): void
    {
        $interceptor = new UnderstudyInterceptor();
        $next = static function (TestInfo $info): TestResult {
            $double = Understudy::for(CollectorContract::class);

            when(static fn() => $double->get())->returns(7);

            return new TestResult(info: $info, status: Status::Passed);
        };

        $result = $interceptor->runTest($this->info(), $next);

        Assert::same($result->status, Status::Passed);
    }

    public function verifiesWithoutTheAssertPluginAttached(): void
    {
        $interceptor = new UnderstudyInterceptor();
        $next = static function (TestInfo $info): TestResult {
            $double = Understudy::for(CollectorContract::class);

            expect(static fn() => $double->get());

            return new TestResult(info: $info, status: Status::Passed);
        };

        $result = $interceptor->runTest($this->info(), $next);

        Assert::same($result->status, Status::Failed);
        Assert::instanceOf($result->failure, VerificationFailed::class);
        // No history to append to, but the verification is still a check the
        // test performed.
        Assert::same($result->summary->metric('assertions'), 1);
    }

    /**
     * An inline test is reset but never verified. Verification would be about
     * setup an inline case is not supposed to have; the reset is about the
     * test after it, which is not inline and did not ask for a leftover.
     */
    public function anInlineTestIsResetButNotVerified(): void
    {
        $interceptor = new UnderstudyInterceptor();
        $next = static function (TestInfo $info): TestResult {
            $double = Understudy::for(CollectorContract::class);

            expect(static fn() => $double->get());

            return CollectedResult::with($info, assertions: 1);
        };

        $result = $interceptor->runTest($this->info(type: 'inline'), $next);

        Assert::same($result->status, Status::Passed);
        Assert::null($result->failure);
        Assert::true(Understudy::idle());
    }

    private function info(string $type = 'test'): TestInfo
    {
        return new TestInfo(
            name: 'check',
            caseInfo: new CaseInfo(
                definition: new CaseDefinition(name: 'Stub', type: $type, file: Path::create(__FILE__)),
                suiteIdentity: new SuiteIdentity('Unit'),
            ),
            testDefinition: new TestDefinition(
                reflection: new \ReflectionMethod(self::class, 'registersTheInterceptorInTheCollector'),
            ),
        );
    }
}
