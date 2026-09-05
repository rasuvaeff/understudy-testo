# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 0.3.1 — 2026-09-05

- Allows `rasuvaeff/understudy` `^0.8 || ^1.0`. The one union this package
  will carry: it bridges the engine's 1.0 release, so that a project on the
  engine's 1.0 can keep this adapter without a window in which `composer
  require` silently installs the 0.8 engine beside it. It is narrowed to
  `^1.0` in the release that follows the engine's — this package stays on
  0.x until Testo itself reaches 1.0, because `UnderstudyPlugin` implements a
  Testo interface whose signature names an `Internal\` class.
- `infection/infection` moves to `^0.35`, the monorepo's single-major form.
- Both READMEs and `llms.txt` stop dating engine behaviour («understudy 0.4+»
  beside `lean()`, «the engine from 0.3.0 refuses»): with a floor of `^0.8`
  every engine this adapter installs beside behaves that way.

## 0.3.0 — 2026-09-05

- Requires `rasuvaeff/understudy` `^0.8`, and requires it as a single term. The
  accumulating union it carried (`^0.4 || ^0.5 || …`) had to be widened by hand
  on every core release, and a package that misses one becomes uninstallable
  beside its own engine.
- **The runner's "this test made no assertion" check is no longer disabled for
  the whole suite.** Verification recorded a success for every completed test
  and added an assertion to it, after which the narrow rule that takes back a
  `Risky` verdict — "our record is the only one in the history" — held for
  tests that had never touched understudy. Installing this adapter for one
  clear place of verification silently cost the project a runner check across
  every test, including ones written before the adapter. A test that created no
  double is now left alone entirely.
- Both READMEs and `llms.txt` stop pointing at `Understudy::strict($double)` as
  the per-double form of strict stubs. It is strict *dispatch* — "fail on any
  call no expectation matched" — and says nothing about a stub that was
  configured and never called; the per-double equivalent is
  `when(…)->times(n)`.
- Both READMEs and `llms.txt` say that verification runs **after** your teardown
  here and **before** it under `understudy-phpunit`, so a test whose expectation
  is fulfilled by teardown itself passes in one runner and fails in the other.
- `llms.txt` mentions that `UnderstudyInterceptor` takes the same `strictStubs`
  argument as `UnderstudyPlugin`.

## 0.2.3 — 2026-09-04

- **Documentation review fixes.** Both READMEs gained the missing Security
  section and now list `testo/assert` in Requirements. AGENTS.md no longer
  links to the retired `_plans/UNDERSTUDY-PLAN.md`. llms.txt header matches
  the family form.

## 0.2.2 — 2026-09-04

- Allow `rasuvaeff/understudy` `^0.7`. Widened rather than raised.
- The claim that a benchmark is reset but never verified is a test now.
  `AGENTS.md` stated it and nothing checked it — and it is the half that has
  broken before: the scope used to be an `InterceptorOptions(testType:)`
  filter, which skipped the reset too, so a `#[TestInline]` double survived
  into the next plain test. The test drives `runTest()` with each
  `TestType` that is not a plain test, which is where the decision is read;
  a real `#[Bench]` fixture would be measuring Testo's benchmark runner
  instead of this adapter.
- `examples/lifecycle.php` is part of `composer build`, and its checks throw
  instead of using `assert()` — which is compiled out under
  `zend.assertions=-1`, so the example silently stopped checking anything.
  The sibling `understudy-phpunit` already worked this way.
- The `Seams` fixture's data provider follows the `<method>Provider`
  convention, and every fixture carries `@internal` — some did, some did not.

## 0.2.1 — 2026-09-04

- Allow `rasuvaeff/understudy` `^0.6`. Widened rather than raised: the adapter
  works against both 0.5 and 0.6, and consumers on 0.5 should not be cut off
  from it.

## 0.2.0 — 2026-09-03

A minor rather than a patch: the floor on `rasuvaeff/understudy` moves to
`^0.5`, which Composer's caret already treats as breaking on 0.x.

- **Requires `rasuvaeff/understudy` `^0.5`.** The wide range this package
  carried was worth keeping while it needed nothing newer; it now does. The
  fixture below states a claim that is only true of core 0.5.0, and against an
  older engine it would either assert the opposite or have to be skipped —
  which is a claim verified nowhere near the floor. Nobody is stranded:
  0.1.4 works against every core from 0.1 to 0.5 and carries the same adapter.
- An integration fixture pins scope isolation from this side of the boundary:
  an enclosing expectation left open, a complete nested `Understudy::scope()`,
  and the enclosing call after it. The interceptor asks the global
  `Understudy::verifyAll()` from a teardown position, which is the shape the
  defect fixed in core 0.5.0 took in practice, and nothing here could see it
  until core 0.5.0 was installable. (#14)

## 0.1.4 — 2026-09-03

- The Requirements section of both READMEs said `rasuvaeff/understudy`
  `^0.1 || ^0.2 || ^0.3` while `composer.json` has allowed `^0.4` since 0.1.3,
  and the usage examples already use 0.4 idioms.
- Allow `rasuvaeff/understudy` `^0.5`. The core release narrows what a
  closing `scope()` verifies and refuses two impossible matcher
  configurations — both changes to the consumer's own test code, neither
  reaching this adapter, which needs no code change.

## 0.1.3 — 2026-08-28

- Allow `rasuvaeff/understudy` `^0.4`: `Arg::rest()`, `Arg::captor()`,
  `Understudy::delegate()`, `Understudy::lean()` and rendered property hooks
  are all additive — the adapter needs no code change.

- README (EN+RU): documented that the adapter's reset runs *after*
  `#[AfterTest]` teardown while the call log still retains returned values,
  the Windows "Directory not empty" failure that surfaces it, and the two
  remedies — `Understudy::lean()` (understudy 0.4+) and `Understudy::scope()`
  (rasuvaeff/understudy#63).

## 0.1.2 — 2026-08-27

- Allow `rasuvaeff/understudy` `^0.3`: the engine now refuses a `when()` and
  an `expect()` naming the exact same call with `ConflictingExpectation`
  (rasuvaeff/understudy#59); the README rule about one expectation per call
  now points at that refusal instead of describing the silent degradation.

- Fix the README/README.ru/llms.txt example: it armed `expect()` after the code under test ran, but an expectation counts only calls that arrive after arming — as written it would fail with "called never". The example now uses `expect(...)->returns(...)` (value and exactly-once in one expectation) declared before the run, and both READMEs document the two engine rules: arm `expect()` before the run (`verify()` is the retrospective tool), and never combine `when()` with `expect()` on the same call — the later declaration takes the dispatch and the earlier one loses its purpose. Found while dogfooding on `rasuvaeff/circuit-breaker` (#6).

## 0.1.1 — 2026-08-27

- Accept `rasuvaeff/understudy` 0.2 alongside 0.1. Nothing in the adapter
  changes; the core's 0.2.0 is additive, and on 0.x Composer's caret treats a
  minor as a boundary, so the constraint has to say so explicitly. Widening it
  breaks no existing install.

- **The release workflow waits for the matrix build instead of judging it
  mid-flight.** A tag pushed right after the merge arrived while master's own
  build was still running, and the guard read a `null` conclusion as a failed
  one, refusing to create the GitHub Release. Hit for real on the core package
  while tagging `v0.1.1`. No effect on the package itself.

## 0.1.0 — 2026-08-25

- Fixed: a double created inside a `#[TestInline]` case leaked into the next
  plain test. The interceptor was filtered by `InterceptorOptions(testType:)`,
  which skipped the reset along with the verification; it now runs for every
  kind of test and verifies only plain ones. Found by the `Seams` fixture, the
  first thing in this package to run a real inline case.
- Real-process fixtures for the runner seams this adapter had no coverage of:
  every status a body reaches on its own (`Skipped`, `Error`, `Risky`,
  `Flaky`, `Failed`, `Passed`), one runtime per dataset of a data provider,
  one per repetition of `#[Repeat]`, a retried body whose verification failure
  is what the retry policy acts on, and a filtered run.
- Initial development: `UnderstudyPlugin` and `UnderstudyInterceptor` —
  verify-after-success, reset-in-`finally`, original-failure precedence,
  optional `strictStubs`.
- Fixed: the `assertions` metric was doubled. `Summary::withAddedMetric()`
  adds to the metric rather than replacing it, and the adapter passed the
  whole history size — a test with `N` assertions reported `2N + 1`.
- The verification is counted even when the assert plugin is not part of the
  suite; only the history record needs the plugin.
- Fixed: a body that finished `Risky` or `Flaky` was never verified. Since a
  test whose only check is an expectation records no assertion of its own and
  is therefore `Risky`, an unmet `expect()` passed silently in precisely the
  tests built around expectations. All three completed statuses are verified.
- A test whose sole check is an understudy expectation is no longer reported
  `Risky`: the adapter takes that verdict back when its record is the only one
  in the history.
- Narrowed `testo/testo` to `^0.10.42` to match the minimum required by
  `testo/assert ^0.1.13`. The previous `^0.10.39 || ^1.0`
  promised support for a major that does not exist on Packagist and cannot be
  tested; 1.x will be opened in a minor once Testo 1.0 ships and is exercised.
- Documented that the verification record does not appear in the printed
  `assert-history` block, and that `#[TestInline]` tests and benchmarks are
  outside the interceptor's scope.
