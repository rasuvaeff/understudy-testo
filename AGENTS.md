# AGENTS.md — understudy-testo

Guidance for AI agents working on this package. Read before changing code.

## What this is

The Testo adapter of the understudy family — a thin layer over
`rasuvaeff/understudy` that ends every test with the library's bookkeeping
done for the user. It ships exactly two classes:

- `Rasuvaeff\Understudy\Testo\UnderstudyPlugin` — `PluginConfigurator`;
  registers the interceptor on a suite (`strictStubs` off by default);
- `Rasuvaeff\Understudy\Testo\UnderstudyInterceptor` — verify-after-success,
  reset-in-`finally`, original-failure precedence.

Everything algorithmic — doubles, expectations, matchers, verification — lives
in the core package; do not fix engine behaviour from here. The design and its
milestones live in the monorepo at `_plans/UNDERSTUDY-PLAN.md` (§6.7 is the
adapter lifecycle contract).

## Golden rules

1. **Verification is mandatory.** Never claim "done" without a fresh green
   `composer build`. "Should work" does not count.
2. **No suppressions.** No `@psalm-suppress`, no baseline. Fix the root cause.
3. **The adapter adds no operations and no state.** Every doubling operation
   belongs to the core facade; the interceptor may only call
   `Understudy::verifyAll()` and `Understudy::reset()`, read nothing internal,
   and keep no mutable fields. A change that grows either list is a design
   decision to be made in the plan first.
4. **Preserve the public contract.** Update README + tests with any API change.

## Commands

No PHP/Composer on the host — run in Docker via the `composer:2` image.

The core package (`rasuvaeff/understudy`) has no release yet, so during
development it resolves through a temporary path repository. Run from the
monorepo root, with the whole root mounted so the sibling package is visible:

```bash
docker run --rm -v "$PWD":/repo -w /repo/understudy-testo composer:2 sh -c '
    git config --global --add safe.directory /repo
    composer config repositories.core "{\"type\":\"path\",\"url\":\"../understudy\",\"options\":{\"versions\":{\"rasuvaeff/understudy\":\"0.1.0\"}}}"
    composer update
    composer config --unset repositories.core
    rm composer.lock
'
```

Never commit that `repositories` key or a `composer.lock`.

Otherwise, as usual:

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 composer build
docker run --rm -v "$PWD":/app -w /app composer:2 composer cs:fix
docker run --rm -v "$PWD":/app -w /app composer:2 composer psalm
docker run --rm -v "$PWD":/app -w /app composer:2 composer test
docker run --rm -v "$PWD":/app -w /app composer:2 composer release-check
```

Or with Make:

```bash
make build
make cs-fix
make psalm
make test
make test-coverage
make mutation
make release-check
```

`make test-coverage` and `make mutation` bootstrap `pcov` inside the
`composer:2` container because the base image has no coverage driver.

## Invariants & gotchas

- **Pipeline position is load-bearing: the interceptor sits just OUTSIDE
  Testo's assert collector** (`ORDER_ASSERTIONS - 100`; the collector runs at
  `ORDER_ASSERTIONS - 10`). Higher order means closer to the test, so moving
  ours inward would put it before the collector attaches the collected
  `TestState` to the result — the attribute would not be there yet, and the
  static state that carries it is `@psalm-internal Testo\Assert`, closed to
  adapters on purpose. If Testo ever moves the collector's order, ours moves
  with it.
- **The lifecycle table (plan §6.7) is the contract.** Passed → verify;
  failed/error/skipped → pass through untouched; always reset in `finally`.
  A teardown error must never replace the test's original failure, and there
  is deliberately no branch where verification runs for a non-passing status.
- **Verification failure becomes a normal assertion-style failure**: recorded
  into the collected history as an `AssertionException`, recounted into the
  `assertions` metric, returned as `Status::Failed` carrying the core's own
  `VerificationFailed`. Never throw past the pipeline — an interceptor
  exception aborts the whole test as `Status::Aborted`.
- **A suite without the assert plugin must still work.** The result carries no
  `TestState` then; verification still runs, only the accounting is skipped.
  Do not "fix" this by requiring `Testo\Assert\TestState` to exist.
- **Namespace placement follows PSR-4, family convention notwithstanding.**
  Classes in `Rasuvaeff\Understudy\Testo\…` live under `src/Testo/`, because
  the shared prefix `Rasuvaeff\Understudy\` maps both packages' `src/`
  directories and PSR-4 appends the sub-namespace path — a file at `src/`
  root is simply never found.
- **Tests drive the interceptor directly** with hand-built `TestInfo`/`TestResult`
  objects and stub `$next` closures (the pattern of property-testing-testo's
  `PropertyInterceptorTest`). Testo's nested-runner helper is
  `@psalm-internal Testo` and unavailable here; real-suite behaviour is
  proven by dogfooding (plan milestone 6), not by unit tests.
- **Every test class resets the context in `#[BeforeTest]`.** The adapter
  resets after each run, but our own suite does not use the plugin — an
  assertion failing mid-scenario would otherwise leak state into the next
  scenario.
- Code: `declare(strict_types=1)`, `final readonly class`, `#[\Override]`,
  explicit types, named arguments.
- `examples/` is part of the public contract: keep scripts runnable and update
  `examples/README.md` when example usage changes.
- **CI workflows are SHA-pinned.** Every `uses:` in `.github/workflows/*.yml`
  references a 40-char commit SHA with a `# vN` trailing comment. Never revert
  to floating `@vN` tags. Updates go through Dependabot. Workflows carry
  `permissions: { contents: read }` and `persist-credentials: false` on every
  checkout. Verify with `zizmor --persona=auditor .github/`.

## When you finish

- Update `README.md` **and `README.ru.md`** (both languages, same commit;
  and `examples/` if usage changed); update `CHANGELOG.md` when releasing.
- Re-run `composer build`; if the change affects public API or release safety,
  also run `make release-check`. Paste the output.
