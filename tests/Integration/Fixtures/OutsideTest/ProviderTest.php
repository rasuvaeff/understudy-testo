<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\Testo\Tests\Integration\Fixtures\OutsideTest;

use Rasuvaeff\Understudy\Understudy;
use Testo\Assert;
use Testo\Data\DataProvider;
use Testo\Test;

use function Rasuvaeff\Understudy\expect;

/**
 * Doubles built by a data provider, outside the test that uses them.
 *
 * A provider runs before the pipeline of the dataset it feeds, so whatever it
 * builds is already in the context when the dataset starts — an array
 * provider builds every dataset's double before the first one runs, a
 * generator builds each just before its own. Either way the guard refuses
 * the dataset rather than verify a double the body did not make.
 *
 * @internal
 */
#[Test]
final class ProviderTest
{
    #[DataProvider('arrayProvider')]
    public function anArrayProviderIsRefused(Gate $gate): void
    {
        $gate->open(1);
    }

    #[DataProvider('generatorProvider')]
    public function aGeneratorProviderIsRefused(Gate $gate): void
    {
        $gate->open(2);
    }

    /**
     * The refusal still resets: the test after it is handed an empty runtime.
     */
    public function theRefusalLeavesNothingBehind(): void
    {
        Assert::true(Understudy::idle());
    }

    /**
     * @return array<string, array{Gate}>
     */
    public static function arrayProvider(): array
    {
        return [
            'first' => [Understudy::for(Gate::class)],
            'second' => [Understudy::for(Gate::class)],
        ];
    }

    /**
     * @return iterable<string, array{Gate}>
     */
    public static function generatorProvider(): iterable
    {
        $gate = Understudy::for(Gate::class);

        expect(static fn() => $gate->open(2));

        yield 'only' => [$gate];
    }
}
