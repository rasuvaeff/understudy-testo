<?php

declare(strict_types=1);

/**
 * The shared assertion helper. The leading underscore marks it an include
 * rather than a script of its own.
 *
 * It throws instead of using assert(): assert() is compiled out under
 * `zend.assertions=-1`, and an example that silently stops checking is worse
 * than one that was never written.
 */
function check(bool $condition, string $what): void
{
    if (!$condition) {
        throw new RuntimeException('FAILED: ' . $what);
    }

    printf("  ok  %s\n", $what);
}
