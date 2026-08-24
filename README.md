# rasuvaeff/understudy-testo

Testo adapter for [rasuvaeff/understudy](https://github.com/rasuvaeff/understudy) —
a test double library where a configured call is a real call:
`when(fn () => $repo->find(123))->returns($book)`.

The plugin ends every test with understudy's own bookkeeping done for you:

- **verify after success** — after a passing body, every `expect()` is checked.
  An expectation the code under test never fulfilled turns the pass into a
  failure;
- **original failure wins** — after a failing or skipped body nothing is
  verified, so the adapter can never mask the error that actually happened;
- **reset always** — the context is dropped after every test, in `finally`.
  One test can never leak a double, an expectation or a stub into the next.

> Using an AI coding assistant? [llms.txt](llms.txt) is a compact API
> reference it can load instead of guessing.

## Requirements

- PHP 8.3 – 8.5
- `rasuvaeff/understudy` (`^0.1`)
- `testo/testo` (`^0.10.39`)

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
use function Rasuvaeff\Understudy\when;

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
        when(static fn() => $books->find(7))->returns($expected = new Book(7));

        $service = new Checkout($books);
        $receipt = $service->charge(cart: [7]);

        Assert::same($receipt->total, $expected->price);
        expect(static fn() => $books->find(7)); // exactly once — verified for you
    }
}
```

If the service never calls `find(7)`, the test fails after its body — with an
unmet-expectation report naming the call, not with a silent green.

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
their doubles isolated, and `reset()` clears only the current context. The
adapter does not copy or replace process state.

## Examples

See [`examples/`](examples/README.md).

## Development

No PHP/Composer on the host — everything runs through Docker:

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 composer build
```

Or with Make: `make build`, `make cs-fix`, `make psalm`, `make test`.

## License

[BSD-3-Clause](LICENSE.md)
