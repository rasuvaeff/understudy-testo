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

    /**
     * @return array{int, string}
     */
    private function runFixture(): array
    {
        $root = dirname(__DIR__, 2);
        $config = __DIR__ . '/Fixtures/RealProcess/testo.php';

        $command = sprintf(
            '%s %s --config=%s --no-ansi 2>&1',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($root . '/vendor/bin/testo'),
            escapeshellarg($config),
        );

        exec($command, $lines, $exit);

        $output = preg_replace('/\e\[[\d;]*m/', '', implode("\n", $lines));

        return [$exit, $output ?? ''];
    }
}
