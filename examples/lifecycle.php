<?php

declare(strict_types=1);

use Internal\Path;
use Rasuvaeff\Understudy\Testo\UnderstudyInterceptor;
use Rasuvaeff\Understudy\Understudy;
use Testo\Assert\TestState;
use Testo\Core\Context\CaseInfo;
use Testo\Core\Context\Identity\SuiteIdentity;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Definition\CaseDefinition;
use Testo\Core\Definition\TestDefinition;
use Testo\Core\Value\Status;

use function Rasuvaeff\Understudy\expect;
use function Rasuvaeff\Understudy\when;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/_check.php';

/**
 * Demonstrates the adapter lifecycle on a simulated pipeline: each scenario is
 * one test body handed to the interceptor through a stub `$next`, the way
 * Testo would run it. In a real suite you never see any of this — you register
 * `UnderstudyPlugin` in testo.php and write plain understudy code.
 */

$interceptor = new UnderstudyInterceptor();

$run = static function (callable $body) use ($interceptor): TestResult {
    $info = new TestInfo(
        name: 'scenario',
        caseInfo: new CaseInfo(
            definition: new CaseDefinition(name: 'Demo', type: 'test', file: Path::create(__FILE__)),
            suiteIdentity: new SuiteIdentity('Examples'),
        ),
        testDefinition: new TestDefinition(reflection: new ReflectionFunction($body)),
    );

    return $interceptor->runTest($info, static fn(TestInfo $info): TestResult => $body($info)
        ->withAttribute(TestState::class, new TestState()));
};

// 1. A passing body whose expectations hold stays passing.
$first = $run(static function (TestInfo $info): TestResult {
    $gate = Understudy::for(Access::class);

    when(static fn() => $gate->open('front'))->returns(true);
    $gate->open('front');

    return new TestResult(info: $info, status: Status::Passed);
});

printf(
    "1) passed body, expectation fulfilled  -> %s\n",
    $first->status->name,
);

// 2. A passing body with an unmet expectation fails after the fact.
$second = $run(static function (TestInfo $info): TestResult {
    $gate = Understudy::for(Access::class);

    expect(static fn() => $gate->open('vault'));

    return new TestResult(info: $info, status: Status::Passed);
});

check($second->failure !== null, 'an unmet expectation fails a passing body');
printf(
    "2) passed body, expectation unmet      -> %s\n   %s\n",
    $second->status->name,
    $second->failure->getMessage(),
);

// 3. A failing body keeps its own failure; verification does not run.
$third = $run(static function (TestInfo $info): TestResult {
    $gate = Understudy::for(Access::class);

    expect(static fn() => $gate->open('vault'));

    return new TestResult(info: $info, status: Status::Failed, failure: new RuntimeException('cart is empty'));
});

check($third->failure !== null, 'a failing body keeps its own failure');
printf(
    "3) failing body keeps its failure      -> %s (%s)\n",
    $third->status->name,
    $third->failure->getMessage(),
);

interface Access
{
    public function open(string $door): bool;
}
