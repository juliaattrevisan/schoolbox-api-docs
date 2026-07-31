# Deterministic Natural-Time Tests Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the Schoolbox `parseNatural()` Jest tests deterministic while preserving the plugin's current Locutus parsing behaviour.

**Architecture:** Change only the existing Jest test file. Use fixed reference times and hand-checked literal results so Day.js arithmetic is no longer used as an oracle for the Locutus-backed plugin; freeze Jest's clock only for the test that deliberately omits the reference argument.

**Tech Stack:** Jest, Day.js, Locutus `strtotime`, JavaScript

## Global Constraints

- Do not change `www/static/js/dayjs/naturalTimePlugin.ts`.
- Preserve the current behaviour of Schoolbox's custom `parseNatural()` plugin.
- Keep the change limited to deterministic test setup and expectations.
- Do not add timezone-specific DST coverage in this ticket.

---

### Task 1: Make the natural-time tests deterministic

**Files:**
- Modify: `www/static/js/dayjs/tests/naturalTimePlugin.test.js`
- Test: `www/static/js/dayjs/tests/naturalTimePlugin.test.js`

**Interfaces:**
- Consumes: `dayjs.parseNatural(text, now?)`, returning a Day.js instance or `false`.
- Produces: deterministic characterisation tests for the plugin's existing parsing contract.

- [ ] **Step 1: Record the existing failing test**

Run:

```bash
npm test -- --runInBand www/static/js/dayjs/tests/naturalTimePlugin.test.js
```

Expected: FAIL at `parses '4 years 2 months and 7 days' correctly` with a
timestamp difference of `86400` seconds on 31 July.

- [ ] **Step 2: Replace live reference times and computed expectations**

Define stable test values:

```js
const dateTimeFormat = "YYYY-MM-DD HH:mm:ss";
const referenceTime = dayjs("2026-01-15T12:00:00");
```

Use `referenceTime` for the minute, hour, day, and unparsable-input tests.
Assert hand-checked literal formatted results:

```js
expect(result.format(dateTimeFormat)).toBe("2026-01-15 12:05:00");
expect(result.format(dateTimeFormat)).toBe("2026-01-15 14:00:00");
expect(result.format(dateTimeFormat)).toBe("2026-01-16 12:00:00");
expect(result.format(dateTimeFormat)).toBe("2026-01-10 12:00:00");
expect(result.format(dateTimeFormat)).toBe("2026-01-15 13:00:00");
expect(result.format(dateTimeFormat)).toBe("2026-01-15 13:02:00");
```

For the test without a supplied reference, freeze the clock and rely on an
`afterEach` cleanup:

```js
afterEach(() => {
    jest.useRealTimers();
});

jest.useFakeTimers();
jest.setSystemTime(referenceTime.toDate());
const result = dayjs.parseNatural("in 5 minutes");
expect(result).not.toBeFalsy();
expect(result.format(dateTimeFormat)).toBe("2026-01-15 12:05:00");
```

Assert the absolute date as a literal:

```js
expect(result.format("YYYY-MM-DD")).toBe("2025-04-27");
```

- [ ] **Step 3: Characterise Locutus calendar-overflow semantics**

Use a fixed leap-day reference for both year tests:

```js
const now = dayjs("2028-02-29T12:00:00");
```

Assert the plugin's current normalization behaviour:

```js
expect(result.format(dateTimeFormat)).toBe("2029-03-01 12:00:00");
expect(result.format(dateTimeFormat)).toBe("2025-03-01 12:00:00");
```

Use a fixed month-end reference for the compound duration:

```js
const now = dayjs("2026-07-31T12:00:00");
```

Assert the existing Locutus result:

```js
expect(result.format(dateTimeFormat)).toBe("2030-10-08 12:00:00");
```

- [ ] **Step 4: Run the focused test**

Run:

```bash
npm test -- --runInBand www/static/js/dayjs/tests/naturalTimePlugin.test.js
```

Expected: PASS with 13 passing tests.

- [ ] **Step 5: Run file-level quality checks**

Run:

```bash
npx eslint --max-warnings=0 www/static/js/dayjs/tests/naturalTimePlugin.test.js
npx prettier --check www/static/js/dayjs/tests/naturalTimePlugin.test.js
```

Expected: both commands exit successfully with no warnings or formatting
changes required.

- [ ] **Step 6: Run the full Jest suite**

Run:

```bash
npm test -- --runInBand
```

Expected: all Jest suites pass.

- [ ] **Step 7: Commit the implementation**

```bash
git add www/static/js/dayjs/tests/naturalTimePlugin.test.js
git commit -m "Make natural-time tests deterministic [SA-23116]"
```
