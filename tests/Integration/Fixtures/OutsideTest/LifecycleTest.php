<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\Testo\Tests\Integration\Fixtures\OutsideTest;

use Rasuvaeff\Understudy\Understudy;
use Testo\Lifecycle\BeforeClass;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

use function Rasuvaeff\Understudy\expect;

/**
 * `#[BeforeClass]` runs once, before any test of the case, so its doubles
 * are refused by the first test. `#[BeforeTest]` runs inside each test's own
 * pipeline, so a double built there belongs to that test and is verified as
 * usual.
 *
 * @internal
 */
#[Test]
final class LifecycleTest
{
    private static ?Gate $shared = null;

    private Gate $gate;

    #[BeforeClass]
    public static function buildShared(): void
    {
        self::$shared = Understudy::for(Gate::class);
    }

    #[BeforeTest]
    public function buildOwn(): void
    {
        $this->gate = Understudy::for(Gate::class);

        expect(fn() => $this->gate->open(3));
    }

    public function aBeforeClassDoubleIsRefused(): void
    {
        self::$shared?->open(4);
    }

    public function aBeforeTestDoubleIsVerified(): void
    {
        $this->gate->open(3);
    }
}
