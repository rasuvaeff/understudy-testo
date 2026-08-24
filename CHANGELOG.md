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
- Documented that the verification record does not appear in the printed
  `assert-history` block, and that `#[TestInline]` tests and benchmarks are
  outside the interceptor's scope.

