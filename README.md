# rasuvaeff/understudy-testo

[![Latest Stable Version](https://poser.pugx.org/rasuvaeff/understudy-testo/v)](https://packagist.org/packages/rasuvaeff/understudy-testo)
[![Total Downloads](https://poser.pugx.org/rasuvaeff/understudy-testo/downloads)](https://packagist.org/packages/rasuvaeff/understudy-testo)
[![Build](https://github.com/rasuvaeff/understudy-testo/actions/workflows/build.yml/badge.svg)](https://github.com/rasuvaeff/understudy-testo/actions/workflows/build.yml)
[![Static analysis](https://github.com/rasuvaeff/understudy-testo/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/rasuvaeff/understudy-testo/actions/workflows/static-analysis.yml)
[![Psalm level](https://img.shields.io/badge/psalm-level_1-blue.svg)](https://github.com/rasuvaeff/understudy-testo/actions/workflows/static-analysis.yml)
[![PHP](https://img.shields.io/packagist/dependency-v/rasuvaeff/understudy-testo/php)](https://packagist.org/packages/rasuvaeff/understudy-testo)
[![License](https://img.shields.io/badge/license-BSD--3--Clause-blue.svg)](LICENSE.md)
[Русская версия](README.ru.md)

Testo adapter for [rasuvaeff/understudy](https://github.com/rasuvaeff/understudy) —
a test double library where a configured call is a real call:
`when(fn () => $repo->find(123))->returns($book)`.

The plugin ends every plain Testo test with understudy's own bookkeeping done
for you:

- **verify after success** — after a passing body, every `expect()` is checked.
  An expectation the code under test never fulfilled turns the pass into a
  failure;
- **original failure wins** — after a failing or skipped body nothing is
  verified, so the adapter can never mask the error that actually happened;
- **reset always** — the context is dropped after every test, in `finally`.
  One test can never leak a double, an expectation or a stub into the next.

Verification is for plain `#[Test]` tests. `#[TestInline]` cases and benchmarks
are not verified — an inline case is meant to be a pure, deterministic
table-driven check with no setup to answer for, and a benchmark would pay for
verification on every iteration. Keep doubles in plain tests. The reset is not
scoped that way: whatever the kind of test, its doubles are dropped when it
ends, so an inline case cannot hand a leftover to the test after it.

> Using an AI coding assistant? [llms.txt](llms.txt) is a compact API
> reference it can load instead of guessing.

## Requirements

- PHP 8.3 – 8.5
- `rasuvaeff/understudy` (`^0.1 || ^0.2 || ^0.3`)
- `testo/testo` (`^0.10.42`)

## Installation

```bash
composer require --dev rasuvaeff/understudy-testo
```

## Usage

Register the plugin on the suites that use doubles:

```php
// testo.php
use Rasuvaeff\Understudy\Testo\UnderstudyPlugin;
use Testo\Application\Config\ApplicationConfig;
use Testo\Application\Config\SuiteConfig;

return new ApplicationConfig(
    src: ['src'],
    suites: [
        new SuiteConfig(
            name: 'Unit',
            location: ['tests'],
            plugins: [new UnderstudyPlugin()],
        ),
    ],
);
```

Then write tests as the core package documents them — no manual cleanup:

```php
<?php

declare(strict_types=1);

namespace App\Tests;

use function Rasuvaeff\Understudy\expect;

use App\Contract\BookRepository;
use Rasuvaeff\Understudy\Understudy;
use Testo\Assert;
use Testo\Test;

#[Test]
final class CheckoutTest
{
    public function chargesForTheCart(): void
    {
        $books = Understudy::for(BookRepository::class);
        expect(static fn() => $books->find(7))->returns($expected = new Book(7)); // exactly once — verified for you

        $service = new Checkout($books);
        $receipt = $service->charge(cart: [7]);

        Assert::same($receipt->total, $expected->price);
    }
}
```

If the service never calls `find(7)`, the test fails after its body — with an
unmet-expectation report naming the call, not with a silent green.

Two rules the engine enforces, easy to miss when coming from Mockery:

- **Arm before the run.** An `expect()` counts only the calls that arrive
  after it is declared — an expectation armed after the subject ran counts
  zero and fails as "called never". A call that has already happened is
  claimed retrospectively by `verify()`, or read from `Understudy::calls()`.
- **One expectation per call.** A `when()` stub and an `expect()` naming the
  exact same call do not compose, and the engine from 0.3.0 refuses the second
  registration outright with `ConflictingExpectation` — on older engines the
  pair degraded silently (the later declaration took the dispatch and the
  earlier one lost its purpose). Either way the idiom is the same: put
  `->returns()` on the `expect()` itself, or `->times()` on the `when()`.

### Strict stubs

```php
new UnderstudyPlugin(strictStubs: true)
```

A stub configured but never called fails its test too — the Mockito reading of
"why did you configure it, then?". Off by default; strictness per double is
available from the core as `Understudy::strict($double)` regardless of this
setting.

## What gets recorded

On a passing test the verification counts as one more assertion of the test:
the `assertions` metric goes up by one and an "expectations verified" record
is appended to the collected `TestState`. A verification failure is recorded
there the same way and reported as the test's failure.

A test whose only check is an understudy expectation is not risky. Testo calls
a passing test risky when it recorded no assertion, and it decides that before
this adapter can contribute the verification — so the adapter takes the verdict
back when its own record is the only one in the history. Tests that also assert
on their own keep whatever verdict they earned.

One place it is not visible: the `assert-history` block Testo prints. The
collector renders that text before returning, and this adapter runs outside
the collector, so the record does not exist yet at rendering time. The count
and the attached state carry it; the printed history does not.

## API

| Member | Purpose |
|---|---|
| `UnderstudyPlugin` | Registers the interceptor on a suite; `strictStubs` off by default |
| `UnderstudyInterceptor` | Verify-after-success, reset-in-`finally`; registered by the plugin |

Everything else — `for()`, `when()`, `expect()`, `verify()`, matchers,
forwarding, `wire()` — belongs to
[rasuvaeff/understudy](https://github.com/rasuvaeff/understudy) and is
documented there. This package adds no operations of its own.

## Fiber isolation

Core runtime contexts are fiber-local, so tests that suspend fibers keep
their doubles isolated. Verification is deliberately wider: `verifyAll()` and
`reset()` reach every context the test put doubles in, including one a
`#[RunInFiber]` body owns — this interceptor never stands in that context, and
before core spanned them an unmet `expect()` inside such a test passed
silently. The adapter itself copies and replaces no process state.

## Examples

See [`examples/`](examples/README.md).

## The understudy family

| Package | What it is |
|---|---|
| [rasuvaeff/understudy](https://github.com/rasuvaeff/understudy) | The engine: doubles, matchers, expectations, verification. |
| **rasuvaeff/understudy-testo** *(this package)* | Testo adapter — verification and reset around every test. |
| [rasuvaeff/understudy-phpunit](https://github.com/rasuvaeff/understudy-phpunit) | PHPUnit and Pest adapter — the same, through a trait. |
| [rasuvaeff/understudy-psalm](https://github.com/rasuvaeff/understudy-psalm) | Psalm plugin — matcher-aware specifications and misuse diagnostics. |
| [rasuvaeff/understudy-phpstan](https://github.com/rasuvaeff/understudy-phpstan) | PHPStan extension — the same for PHPStan, plus its own rules. |

## Development

No PHP/Composer on the host — everything runs through Docker:

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 composer build
```

Or with Make: `make build`, `make cs-fix`, `make psalm`, `make test`.

## License

[BSD-3-Clause](LICENSE.md)
