<?php

declare(strict_types=1);

use Rasuvaeff\Understudy\Testo\UnderstudyPlugin;
use Testo\Application\Config\ApplicationConfig;
use Testo\Application\Config\SuiteConfig;

return new ApplicationConfig(
    src: ['tests/Integration/Fixtures/Seams'],
    suites: [
        new SuiteConfig(
            name: 'Fixture',
            location: ['tests/Integration/Fixtures/Seams'],
            plugins: [new UnderstudyPlugin()],
        ),
    ],
);
