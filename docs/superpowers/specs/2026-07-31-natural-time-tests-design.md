# Deterministic Natural-Time Tests

## Purpose

Make the natural-time plugin tests deterministic while continuing to test the
calendar semantics provided by Locutus `strtotime`.

## Scope

Only `www/static/js/dayjs/tests/naturalTimePlugin.test.js` will change. The
Day.js plugin and its parsing behaviour will remain unchanged.

## Design

- Use one fixed, ordinary reference time for relative-time examples that are
  not intended to exercise calendar edge cases.
- Freeze Jest's system clock for the test that deliberately omits the optional
  reference time, then restore real timers after that test.
- Replace the live-clock compound-duration expectation with a fixed month-end
  reference and an explicit Locutus-compatible result.
- Add fixed leap-day expectations for forward and backward year parsing so the
  difference between Locutus normalization and Day.js clamping is intentional
  and protected.
- Assert exact results because fixed reference times remove the need for
  one-second tolerances.

## Verification

- Demonstrate the original focused suite failure before editing.
- Run the focused Jest test after editing.
- Run ESLint and Prettier checks for the changed test file.
- Run the full Jest suite.

## Non-goals

- Changing Locutus or Day.js parsing semantics.
- Refactoring the production plugin.
- Adding timezone-specific DST tests, which would require controlling the test
  process timezone and is separate from the reported calendar-overflow bug.
