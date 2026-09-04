<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\Testo\Tests\Integration\Fixtures\Seams;

/**
 * @internal
 */
interface Gate
{
    public function open(int $id): void;
}
