<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\Testo\Tests\Support;

use Testo\Pipeline\Interceptor;
use Testo\Pipeline\InterceptorCollector;

/**
 * @api
 */
final class CollectingCollector implements InterceptorCollector
{
    /** @var list<Interceptor|class-string<Interceptor>> */
    public array $interceptors = [];

    #[\Override]
    public function addInterceptor(Interceptor|string $interceptor): void
    {
        $this->interceptors[] = $interceptor;
    }
}
