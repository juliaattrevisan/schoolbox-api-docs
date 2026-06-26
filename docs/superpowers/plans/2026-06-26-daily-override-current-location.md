# Daily Override Current Location Fix — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix the Current Location field on the student profile so it reflects daily org overrides (substitutions) from external SIS connectors, by ensuring the expensive timetable path (`getTimetableForDate`) is always taken for today's date when no connector config is available.

**Architecture:** A single-line change in `Calendar_Api_EventType_Timetable_ExternalConstrained_Default::getEvents()`. When the class is instantiated with `CONFIGURATION_NONE` the config load always fails, leaving `$days = 0` and making `$showExpensive` permanently false. Adding `$days = 1` to the catch block ensures today's timetable always uses the expensive (substitution-aware) path. No other files are touched.

**Tech Stack:** PHP 8.4, PSR-12, PHPStan level 4, PHPUnit

**Spec:** `docs/superpowers/specs/2026-06-26-daily-override-current-location-design.md`

## Global Constraints

- Target branch: `develop` only (`hotfix-develop/SA-22127-1`)
- PHP 8.4, PSR-12 enforced via PHPCS (`phpcs.xml`)
- PHPStan level 4 (`phpstan.neon`)
- Do not modify any file outside the one listed below
- Do not add comments or docblocks to unchanged code
- Run all verification commands inside `docker/ssh`

---

### Task 1: Fix the catch-block `$days` default

**Files:**
- Modify: `library/Calendar/Api/EventType/Timetable/ExternalConstrained/Default.php:101-106`

**Interfaces:**
- Produces: `getEvents()` now calls `buildTimetableExpensively()` (→ `getTimetableForDate`) for today's date when invoked via `produceForTimetableEvents()`, honoring daily org overrides in any connector whose `getTimetableForDate` differs from `getDayTimetable` (Synergetic, Schooledge, PCSchool).

---

> **Note on unit tests:** `Calendar_Api_EventType_Timetable_ExternalConstrained_Default` instantiates `External_Timetable` directly in its constructor and depends on static singletons and live DB calls throughout `getEvents()`. It cannot be unit tested in isolation without out-of-scope refactoring. The verification strategy is: static analysis + full PHPUnit regression suite + manual testing against a Synergetic staging instance.

---

- [ ] **Step 1: Make the change**

In `library/Calendar/Api/EventType/Timetable/ExternalConstrained/Default.php`, locate the catch block at line 101 and add `$days = 1`:

```php
        } catch (\Schoolbox\Mapper\Exception $e) {
            /* Something went awry with loadConnectorConfiguration()
             * Can't determine what event type this would be
             */
            $eventType = null;
            $days = 1; // no config available (CONFIGURATION_NONE) — default to expensive for today
        }
```

The full block in context (lines 89–106) should read:

```php
        // Figure out event type and days to fetch calendar for
        $days = 0;
        try {
            /** @var Calendar_Api_Config_Record $config */
            $config        = $this->loadConnectorConfiguration();
            $decodedConfig = $config->decodeConfig();
            $eventType     = $decodedConfig->getLuEventType();
            // it should always be one of these, but test
            if ($decodedConfig instanceof Calendar_Api_Config_Connector_ExternalConstrained) {
                // ensure this value is a non-negative integer
                $days = max(0, $decodedConfig->getDuration());
            }
        } catch (\Schoolbox\Mapper\Exception $e) {
            /* Something went awry with loadConnectorConfiguration()
             * Can't determine what event type this would be
             */
            $eventType = null;
            $days = 1; // no config available (CONFIGURATION_NONE) — default to expensive for today
        }
```

- [ ] **Step 2: Run PHPCS on the changed file**

```bash
./vendor/bin/phpcs library/Calendar/Api/EventType/Timetable/ExternalConstrained/Default.php
```

Expected: no output (zero violations).

- [ ] **Step 3: Run PHPStan on the changed file**

```bash
./vendor/bin/phpstan analyze library/Calendar/Api/EventType/Timetable/ExternalConstrained/Default.php
```

Expected: `[OK] No errors`.

- [ ] **Step 4: Run the full PHPUnit suite**

```bash
./vendor/bin/phpunit
```

Expected: green suite, same pass/fail counts as before the change. Any new failures indicate a regression and must be investigated before proceeding.

- [ ] **Step 5: Commit**

```bash
git add library/Calendar/Api/EventType/Timetable/ExternalConstrained/Default.php
git commit -m "Default to expensive timetable path when no connector config available [SA-22127]"
```

- [ ] **Step 6: Manual verification (Synergetic staging)**

Using Perth College's staging Synergetic instance (`Synergetic_AUWA_PC_TST`) connected to a Schoolbox staging environment:

1. Set up a student with a known external ID (e.g. Jana Reed → external ID `507`).
2. Set the External Timetable Code for the relevant campus to `SEC`, `SEN`, or `PRI`.
3. Set the external cache timeout to `0` (Admin > External > Settings) so live data is always fetched.
4. In Synergetic, apply a room or class override (daily org change) for a period that is currently in progress.
5. On the student's Schoolbox profile, check the **Current Location** field.
6. On the student's **Dashboard Timetable**, check the same period.

Expected: both the Current Location field and the Dashboard Timetable show the overridden room/class. Before this fix, Current Location would show the cyclic timetable value while the Dashboard showed the override.
