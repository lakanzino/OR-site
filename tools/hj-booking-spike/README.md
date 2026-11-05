# Disposable booking experiments — not installable

Never install on a live site. The files in this directory are guarded CLI-only fixtures for an empty GitHub Actions WordPress/MariaDB service. They have no public endpoints, UI, real user data or activation hooks. They are not the production backend, are not included or loaded by `hamyar-doctors`, and are not a release package.

## Experiments

- The original single-start unique-index spike is deliberately inadequate: 30-minute starts 09:00 and 09:15 both insert, demonstrating why a unique start cannot prevent overlapping intervals.
- `interval-engine.php` experiments with an InnoDB doctor-row `SELECT ... FOR UPDATE` on a **dedicated non-persistent mysqli connection**. The same transaction reads the synthetic working window, duration and buffer, checks half-open overlap against active reservations, and writes the synthetic reservation. Configuration and cancellation also obtain this row lock. Reconnection is never automatic during the critical section; deadlocks/lock timeouts are retried at most twice after rollback. A lost connection/commit result is reported as unknown and can only be reconciled by retrying the same idempotency key.
- Each booking stores the buffered occupied end calculated at booking time. The new buffered end and existing buffered ends both participate in conflict detection. Changes apply to subsequent reservations, without retroactively shortening existing occupied intervals.
- `policy-validation.php` separately tests mobile normalization, national-code checksum, and a WordPress role/ownership policy with an explicit administrator-controlled secretary mapping. **This policy is not wired to the interval operations**, so these are not endpoint authorization tests.

## Verified CI

[42118298327](https://github.com/lakanzino/OR-site/actions/runs/42118298327), commit `d8c0096`, ran on PHP 8.4 and MariaDB 11.4 in disposable WordPress: ten synthetic appointments; overlapping and adjacent intervals; duration and buffer changes; cancellation/rebooking; twelve simultaneous identical starts with one creation; twelve overlapping distinct starts with one creation; twelve contenders across four independent groups with four mutually nonoverlapping winners; twelve simultaneous retries of one idempotency key with one row; a real server-side connection kill after insert before commit followed by retry; simulated response loss after a successful commit followed by replay; real InnoDB lock timeouts with bounded retry and recovery; standalone WordPress role/ownership and checksum tests. These are finite tests, not a proof covering every failure or infrastructure configuration. The post-commit response loss is a controlled simulation, not a network fault injected during COMMIT.

## Stage 2 remains open

This is not complete CRUD or a transfer-ready backend: it has no versioned production migration, no personal data storage, no integrated authorization or nonce, no WP `working_hours` parsing, timezone/DST, enabled/blocked-day policy, privacy or retention implementation, and no integrated testing of those behaviors. The synthetic test uses a single UTC window, not real opening hours or slot generation. The doctor lock only protects writes that actually use it; existing ACF/metadata changes do not take that lock. Schedule updates must be integrated with the same serialization scheme before relying on it. The direct mysqli connection and fixed fixture table names also require a reviewed production adaptation respecting WordPress DB configuration and multisite. Do not claim production readiness or close the full phase-2 gate on this evidence.

See `docs/booking/BOOKING-ANALYSIS.md` and `docs/booking/BOOKING-SCHEMA.md`.
