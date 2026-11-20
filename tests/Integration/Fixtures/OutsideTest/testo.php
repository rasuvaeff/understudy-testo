<?php

declare(strict_types=1);

use Rasuvaeff\Understudy\Testo\UnderstudyPlugin;
use Testo\Application\Config\ApplicationConfig;
use Testo\Application\Config\SuiteConfig;

return new ApplicationConfig(
    src: ['tests/Integration/Fixtures/OutsideTest'],
    suites: [
        new SuiteConfig(
            name: 'Fixture',
            location: ['tests/Integration/Fixtures/OutsideTest'],
            plugins: [new UnderstudyPlugin()],
        ),
    ],
);
