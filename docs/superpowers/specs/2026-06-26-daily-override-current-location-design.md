# SA-22127: Daily Override Support in Current Location Field

**Date:** 2026-06-26
**Branch target:** `develop` only
**Jira:** SA-22127 — Disparity in the location information displayed in Student dashboard and the Student profile

---

## Problem

When a room or class is changed via a daily org override (substitution) in an external SIS, the Dashboard Timetable correctly reflects the change but the Current Location field on the student profile does not.

The bug affects any SIS connector where `getTimetableForDate` returns different data to `getDayTimetable`. At present this is observed in Synergetic (which fetches substitutions from a dedicated stored proc), but Schooledge and PCSchool also have genuinely different implementations of the two methods and could be affected.

---

## Root Cause

`Calendar_Api_EventType_Timetable_ExternalConstrained_Default::getEvents()` has two timetable-building paths:

- **Cheap** (`getDayTimetable`): uses the cyclic timetable pattern; no daily overrides
- **Expensive** (`getTimetableForDate`): uses the date-specific data source; includes substitutions and daily org changes

Which path is used is controlled by `$expensiveWindow`, derived from a connector config value `$days`. The expensive path is taken only when the time elapsed since start-of-day falls within `[0, $expensiveWindow)`.

When `getEvents()` is called via `Calendar_Api_Factory::produceForTimetableEvents()`, the class is instantiated with `Calendar_Api_Config_Mapper::CONFIGURATION_NONE` (`-1`) as the connector ID — a deliberate placeholder meaning no real config exists. The `loadConnectorConfiguration()` call always throws `\Schoolbox\Mapper\Exception` for this ID, so the catch block is always entered. `$days` remains at its initial value of `0`, making `$expensiveWindow = 0` and `$showExpensive` permanently `false`.

The cheap path is therefore always taken, silently ignoring daily overrides.

---

## Decision: Fix the root cause in the shared event type class

Two alternative fix locations were considered:

| Option | Location | Scope |
|--------|----------|-------|
| A (chosen) | `Calendar_Api_EventType_Timetable_ExternalConstrained_Default` | Fixes all consumers (current location, dashboard, full calendar) via the standard `External_TimetableInterface` |
| B (rejected) | `Schoolbox\Profile\Field\Factory` | Fixes current location only; bypasses the calendar API abstraction |

Option A is preferred because it corrects the underlying behaviour once for all callers, aligns with the existing interface design, and matches the original intent that today's timetable should always use the expensive (accurate) path.

A Synergetic-specific workaround (the approach in PR #34752) was also considered and rejected — it hardcodes connector detection in a layer that should not know about connector types, and would need extending for every future connector with daily override support.

---

## Solution

**File:** `library/Calendar/Api/EventType/Timetable/ExternalConstrained/Default.php`
**Method:** `getEvents()`

Add `$days = 1` to the catch block so that when no config can be loaded (the `CONFIGURATION_NONE` case), the expensive window defaults to one day rather than zero:

```php
// Before
$days = 0;
try {
    $config        = $this->loadConnectorConfiguration();
    $decodedConfig = $config->decodeConfig();
    $eventType     = $decodedConfig->getLuEventType();
    if ($decodedConfig instanceof Calendar_Api_Config_Connector_ExternalConstrained) {
        $days = max(0, $decodedConfig->getDuration());
    }
} catch (\Schoolbox\Mapper\Exception $e) {
    $eventType = null;
}

// After
$days = 0;
try {
    $config        = $this->loadConnectorConfiguration();
    $decodedConfig = $config->decodeConfig();
    $eventType     = $decodedConfig->getLuEventType();
    if ($decodedConfig instanceof Calendar_Api_Config_Connector_ExternalConstrained) {
        $days = max(0, $decodedConfig->getDuration());
    }
} catch (\Schoolbox\Mapper\Exception $e) {
    $eventType = null;
    $days = 1; // no config available (CONFIGURATION_NONE) — default to expensive for today
}
```

With `$days = 1`, `$expensiveWindow = 86400` seconds. Today's `$elapsedTime = 0` satisfies `0 < 86400`, so `$showExpensive` is `true` and `buildTimetableExpensively()` (which calls `getTimetableForDate`) is used.

### What does NOT change

- **Real connector configs are unaffected.** When `loadConnectorConfiguration()` succeeds, `$days` is set from the config value and the catch-block default is never reached. A connector admin who has deliberately set `Duration = 0` retains that behaviour.
- **Data model is unchanged.** The twig template for the current location field consumes the same fields (`title`, `periodName`, `periodCancelled`, `location`, `staff`, `startTime`, `endTime`) regardless of which timetable path produced them.
- **No other files are touched.** `Factory.php`, `Calendar/Api/Factory.php`, and all SIS connector implementations are unchanged.

### Performance

The expensive path enriches all periods in the day (staff + category DB lookups per period) before filtering to the current one. A direct `getTimetableForDate` call from `Factory.php` could skip enriching non-current periods, but the saving is negligible: a school day has ~5–10 periods, lookups are likely cached, and the current location field is a per-user-profile render not a bulk operation. The architectural benefit of fixing the root cause once in the shared class outweighs the minor extra work.

---

## Testing

This class has no existing unit tests and constructs `External_Timetable` directly in its constructor, making unit isolation impractical without out-of-scope refactoring.

Verification approach:
- **Manual (primary):** Test with a Synergetic school that has a daily org override in place. Perth College's staging Synergetic instance (`Synergetic_AUWA_PC_TST`) is already connected to a Schoolbox staging environment (per SA-22127 Jira comments). Steps: apply a room/class override in Synergetic for a current period; confirm the Student Profile Current Location field matches the Dashboard Timetable.
- **Regression:** Run the full `./vendor/bin/phpunit` suite to confirm no regressions in other calendar/timetable paths.

---

## Files changed

| File | Change |
|------|--------|
| `library/Calendar/Api/EventType/Timetable/ExternalConstrained/Default.php` | Add `$days = 1` in the `catch` block |
