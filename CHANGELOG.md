# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Unreleased

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
- Narrowed `testo/testo` to `^0.10.39`. The previous `^0.10.39 || ^1.0`
  promised support for a major that does not exist on Packagist and cannot be
  tested; 1.x will be opened in a minor once Testo 1.0 ships and is exercised.
- Documented that the verification record does not appear in the printed
  `assert-history` block, and that `#[TestInline]` tests and benchmarks are
  outside the interceptor's scope.

