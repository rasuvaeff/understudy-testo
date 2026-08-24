<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\Testo;

use Internal\Container\Container;
use Testo\Common\PluginConfigurator;
use Testo\Pipeline\InterceptorCollector;

/**
 * Registers {@see UnderstudyInterceptor} so every test of the suite ends with
 * understudy's own bookkeeping done for it: expectations are verified after a
 * passing body and the context is dropped either way.
 *
 * ```php
 * // testo.php
 * new SuiteConfig(
 *     name: 'Unit',
 *     location: ['tests'],
 *     plugins: [new UnderstudyPlugin()],
 * );
 * ```
 *
 * With `strictStubs: true` a stub that was never called fails its test too.
 *
 * @api
 */
final readonly class UnderstudyPlugin implements PluginConfigurator
{
    public function __construct(
        private bool $strictStubs = false,
    ) {}

    #[\Override]
    public function configure(Container $container): void
    {
        $container->get(InterceptorCollector::class)
            ->addInterceptor(new UnderstudyInterceptor(strictStubs: $this->strictStubs));
    }
}
