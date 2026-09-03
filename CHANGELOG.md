# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
