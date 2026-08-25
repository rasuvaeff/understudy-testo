<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\Testo\Tests\Integration\Fixtures\Statuses;

interface Gate
{
    public function open(int $id): void;
}
