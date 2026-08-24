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

    private function info(): TestInfo
    {
        return new TestInfo(
            name: 'check',
            caseInfo: new CaseInfo(
                definition: new CaseDefinition(name: 'Stub', type: 'test', file: Path::create(__FILE__)),
                suiteIdentity: new SuiteIdentity('Unit'),
            ),
            testDefinition: new TestDefinition(
                reflection: new \ReflectionMethod(self::class, 'registersTheInterceptorInTheCollector'),
            ),
        );
    }
}
