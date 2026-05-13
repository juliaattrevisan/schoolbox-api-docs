# Resource Booking Report Capacity Columns Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Update the Resource Booking report table for SA-22370 so it displays `Start`, `End`, `Booking`, `Venue/Resource`, `Quantity`, and `Notes` in that order.

**Architecture:** The report is a server-rendered Twig page backed by `Schoolbox\ResourceBooking\Report\Controller`. The table is local to `library/Schoolbox/ResourceBooking/Report/templates/index.twig`; it is not a shared report/table component. Keep the change focused on display and pass the current implicit per-booking quantity from the controller into Twig.

**Tech Stack:** PHP 8.4, Aura MVC controller, Twig, DataTables, Codeception smoke tests.

---

## Scope

In scope:

- Rename the report table headers to `Booking`, `Venue/Resource`, and `Quantity`.
- Insert the `Quantity` column between `Venue/Resource` and `Notes`.
- Show the current implicit booking quantity, `CapacityAllocation::BOOKING_QUANTITY`, for resource booking rows.
- Leave the `Quantity` cell blank for non-booking rows such as timetable/class rows.
- Preserve existing links, notes rendering, dates, report filters, and clash filtering.
- Add a smoke test proving the column order and quantity value for a capacity-enabled resource booking.

Out of scope:

- Adding a new per-booking quantity field.
- Changing resource capacity storage.
- Changing booking creation, approval, rejection, clash, or filter logic.
- Refactoring the report into a reusable table component.
- Changing unrelated Resource Booking tests or the existing Codeception `_before` hook naming issue.

## Investigation Notes

- Route registration is in `conf/modules/ResourceBooking.php`; `/resource-booking/report` maps to `Schoolbox\ResourceBooking\Report\Module`.
- The report controller is `library/Schoolbox/ResourceBooking/Report/Controller.php`.
- The only Resource Booking report table is `library/Schoolbox/ResourceBooking/Report/templates/index.twig`.
- The current table headers are at `index.twig:185-189`: `Start`, `End`, `Name`, `Venue`, `Notes`.
- The existing booking context logic is already in the third column through `getTitleForResourceBooking()` when available.
- The existing venue/resource display logic is already in the fourth column and handles both resource booking events and timetable/class rows.
- Current capacity allocation has no per-booking quantity storage; each booking consumes `Schoolbox\ResourceBooking\Booking\CapacityAllocation::BOOKING_QUANTITY`, currently `1`.

## Files

- Modify: `library/Schoolbox/ResourceBooking/Report/Controller.php`
- Modify: `library/Schoolbox/ResourceBooking/Report/templates/index.twig`
- Modify: `test/codeception/Smoketests/ResourceBookingCest.php`

Do not modify `vendor/`, `node_modules/`, report filters, booking validation, resource capacity schema, or booking payload contracts.

---

### Task 1: Add Failing Report Smoke Test

**Files:**
- Modify: `test/codeception/Smoketests/ResourceBookingCest.php`

- [ ] **Step 1: Insert the failing test**

Add this method after `readResourceDisplaysCapacityAndConflictBookingRules()` in `test/codeception/Smoketests/ResourceBookingCest.php`:

```php
    public function bookingReportDisplaysCapacityColumnsAndQuantity(IntegrationTester $i): void
    {
        $resource = $this->createCapacityTestResource(
            $i,
            'Capacity report resource',
            7,
            false,
            ModerationType\Model::TYPE_OPEN
        );
        $startDate = date('d/m/Y g:ia', strtotime('2077-02-01 10:00:00'));
        $endDate = date('d/m/Y g:ia', strtotime('2077-02-01 11:00:00'));

        $booking = $this->createCapacityTestBooking(
            $i,
            $resource,
            'Capacity report booking',
            $startDate,
            $endDate
        );

        $i->sendGET('/resource-booking/report?start=01%2F02%2F2077&end=01%2F02%2F2077&category%5B%5D=3&clashesOnly=0&classes=0');

        $i->seeResponseCodeIs(200);
        $response = $i->grabResponse();

        $lastHeaderPosition = -1;
        foreach (
            [
                '<th>Start</th>',
                '<th>End</th>',
                '<th>Booking</th>',
                '<th>Venue/Resource</th>',
                '<th>Quantity</th>',
                '<th>Notes</th>',
            ] as $header
        ) {
            $headerPosition = strpos($response, $header);
            $i->assertNotFalse($headerPosition, $header . ' header is present');
            $i->assertGreaterThan($lastHeaderPosition, $headerPosition, $header . ' header appears in order');
            $lastHeaderPosition = $headerPosition;
        }

        $i->assertMatchesRegularExpression(
            '~'
            . preg_quote($booking->getName(), '~')
            . '.*?'
            . preg_quote($resource->getName(), '~')
            . '.*?<td>\s*1\s*</td>'
            . '.*?<article><p>These are some notes</p></article>'
            . '~s',
            $response,
            'Report row shows booking, venue/resource, implicit quantity, and notes in order'
        );
    }
```

- [ ] **Step 2: Run the focused test and confirm it fails**

Run:

```bash
docker/compose exec schoolbox-cli ./vendor/bin/codecept run Smoketests ResourceBookingCest:bookingReportDisplaysCapacityColumnsAndQuantity
```

Expected: FAIL because `<th>Booking</th>` and `<th>Quantity</th>` are not present yet.

---

### Task 2: Expose Booking Quantity and Update Report Columns

**Files:**
- Modify: `library/Schoolbox/ResourceBooking/Report/Controller.php`
- Modify: `library/Schoolbox/ResourceBooking/Report/templates/index.twig`
- Test: `test/codeception/Smoketests/ResourceBookingCest.php`

- [ ] **Step 1: Import the capacity allocation helper**

In `library/Schoolbox/ResourceBooking/Report/Controller.php`, add the import beside the other Resource Booking imports:

```php
use Schoolbox\ResourceBooking\Booking\CapacityAllocation;
```

- [ ] **Step 2: Use the imported helper in the controller**

Replace the fully qualified instantiation in `index()`:

```php
        $capacityAllocation = new \Schoolbox\ResourceBooking\Booking\CapacityAllocation();
```

with:

```php
        $capacityAllocation = new CapacityAllocation();
```

- [ ] **Step 3: Pass the implicit booking quantity to Twig**

In the `responseFromTemplate()` data array in `library/Schoolbox/ResourceBooking/Report/Controller.php`, add `bookingQuantity` next to `eventList`:

```php
                'categoryList'     => $categoryList,
                'eventList'        => $eventList,
                'bookingQuantity'  => CapacityAllocation::BOOKING_QUANTITY,
                'search'           => [
                    'start'        => $start,
                    'end'          => $end,
                    'category'     => $selectedCategories,
                    'classes'      => $showClasses,
                    'clashesOnly'  => $clashesOnly,
                ],
```

Keep the existing `search` values unchanged.

- [ ] **Step 4: Update the table headers**

In `library/Schoolbox/ResourceBooking/Report/templates/index.twig`, replace the current header block:

```twig
                    <th>Start</th>
                    <th>End</th>
                    <th>Name</th>
                    <th>Venue</th>
                    <th>Notes</th>
```

with:

```twig
                    <th>Start</th>
                    <th>End</th>
                    <th>Booking</th>
                    <th>Venue/Resource</th>
                    <th>Quantity</th>
                    <th>Notes</th>
```

- [ ] **Step 5: Add the Quantity cell between Venue/Resource and Notes**

In `library/Schoolbox/ResourceBooking/Report/templates/index.twig`, add this cell immediately after the existing venue/resource column `</td>` and before the notes column:

```twig
                        <td>
                            {% if booking is empty %}
                                &nbsp;
                            {% else %}
                                {{ bookingQuantity }}
                            {% endif %}
                        </td>
```

The row order must be:

```text
Start cell
End cell
Booking cell
Venue/Resource cell
Quantity cell
Notes cell
```

- [ ] **Step 6: Run the focused test and confirm it passes**

Run:

```bash
docker/compose exec schoolbox-cli ./vendor/bin/codecept run Smoketests ResourceBookingCest:bookingReportDisplaysCapacityColumnsAndQuantity
```

Expected: PASS.

---

### Task 3: Verify the Report Change

**Files:**
- Verify: `library/Schoolbox/ResourceBooking/Report/Controller.php`
- Verify: `library/Schoolbox/ResourceBooking/Report/templates/index.twig`
- Verify: `test/codeception/Smoketests/ResourceBookingCest.php`

- [ ] **Step 1: Run the full Resource Booking smoke test file**

Run:

```bash
docker/compose exec schoolbox-cli ./vendor/bin/codecept run Smoketests ResourceBookingCest
```

Expected: PASS, with one more test than the current baseline.

- [ ] **Step 2: Run backend style checks for the changed PHP controller**

Run:

```bash
docker/compose exec schoolbox-cli ./vendor/bin/phpcs library/Schoolbox/ResourceBooking/Report/Controller.php
```

Expected: PASS.

- [ ] **Step 3: Run backend style checks for the changed Cest file**

Run:

```bash
docker/compose exec schoolbox-cli ./vendor/bin/phpcs test/codeception/Smoketests/ResourceBookingCest.php
```

Expected: FAIL only on the pre-existing Codeception hook naming rule for `_before()` near line 44. Do not rename `_before()` as part of SA-22370 because it is an existing framework hook and outside this display-only ticket.

- [ ] **Step 4: Check whitespace**

Run:

```bash
git diff --check
```

Expected: no output and exit code `0`.

- [ ] **Step 5: Manually inspect the local report page**

Open the report with a date range containing the smoke-test booking or equivalent test data:

```text
https://docker.dev.schoolbox.cloud/resource-booking/report?start=01%2F02%2F2077&end=01%2F02%2F2077&category%5B%5D=3&clashesOnly=0&classes=0
```

Expected table header order:

```text
Start | End | Booking | Venue/Resource | Quantity | Notes
```

Expected booking row behavior:

```text
Booking: Capacity report booking ...
Venue/Resource: Capacity report resource ...
Quantity: 1
Notes: These are some notes
```

- [ ] **Step 6: Commit**

Run:

```bash
git add library/Schoolbox/ResourceBooking/Report/Controller.php library/Schoolbox/ResourceBooking/Report/templates/index.twig test/codeception/Smoketests/ResourceBookingCest.php
git commit -m "Update resource booking report columns [SA-22370]"
```

Expected: commit succeeds without skipping hooks.

---

## Self-Review

Spec coverage:

- New columns and order are covered by the smoke test header-order assertions and the Twig header change.
- `Quantity` is covered by the smoke test row regex and the new Twig quantity cell.
- Existing report data rendering is preserved by leaving the date, booking title, venue/resource, and notes logic intact.
- `Start` and `End` remain separate columns.
- `Venue/Resource` preserves existing venue behavior and independently displays resource names for booking rows.
- `Notes` remains the final column.
- Shared component impact was reviewed: the Resource Booking report table is local to `index.twig`.
- The Jira ticket is already linked to `SA-21670` and assigned to Delta Team.

Residual risk:

- The current system has implicit per-booking quantity only. The `Quantity` column should therefore display `1` for booking rows until a future ticket introduces stored per-booking quantity.
