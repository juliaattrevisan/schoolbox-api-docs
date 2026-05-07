# Resource Booking Capacity Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add capacity storage and create/read/update support for resource booking resources without changing booking conflict logic.

**Architecture:** Add `capacity` to the existing `resource` table and wire it through the existing `ResourceBooking\Item` model, mapper, controller, shared create/edit Twig form, and resource test payloads. The database and model both default to `1`; server-side POST validation only accepts whole numbers from `1` to `999`.

**Tech Stack:** PHP 8.4, Aura MVC, Schoolbox Mapper, Twig, MariaDB update scripts, Codeception smoke tests, PHPCS, PHPStan.

---

## File Structure

- Modify `sql/initial.sql`: add `capacity` to the `resource` table definition with `int(11) NOT NULL DEFAULT 1`.
- Create `updates/26.1.alpha1/resourceBooking/01-addResourceCapacityField.sql`: add the column for existing installs. MariaDB backfills existing rows to `1` because the column is `NOT NULL DEFAULT 1`.
- Modify `library/Schoolbox/ResourceBooking/Item/Model.php`: add a defaulted `capacity` property, accessors, and JSON serialization.
- Modify `library/Schoolbox/ResourceBooking/Item/Mapper.php`: map `capacity` as `TYPE_INTEGER`.
- Modify `library/Schoolbox/ResourceBooking/Item/Controller.php`: parse and validate `capacity` from POST before setting it on the model.
- Modify `library/Schoolbox/ResourceBooking/Item/templates/edit.twig`: add client-side validation and a number input labelled `Capacity of resource` below the `Allow Conflicting Booking Requests` toggle at one-third width.
- Modify `test/codeception/Support/Payload/ResourceBooking/Resource/Create.php`: default payload capacity to `1` and expose `setCapacity()`.
- Modify `test/codeception/Support/Payload/ResourceBooking/Resource/Update.php`: default payload capacity to `1` and expose `setCapacity()`.
- Modify `test/codeception/Smoketests/ResourceBookingCest.php`: assert default create capacity, update persistence, and invalid create validation.

## Task 1: Database Storage

**Files:**
- Modify: `sql/initial.sql`
- Create: `updates/26.1.alpha1/resourceBooking/01-addResourceCapacityField.sql`

- [ ] **Step 1: Add migration script**

Create `updates/26.1.alpha1/resourceBooking/01-addResourceCapacityField.sql`:

```sql
ALTER TABLE resource
ADD COLUMN capacity int(11) NOT NULL DEFAULT 1 AFTER `allow_clash_bookings`;
```

- [ ] **Step 2: Update initial schema**

In `sql/initial.sql`, update the `resource` table columns from:

```sql
  `current_term_restriction` tinyint(1) NOT NULL DEFAULT 0,
  `allow_clash_bookings` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
```

to:

```sql
  `current_term_restriction` tinyint(1) NOT NULL DEFAULT 0,
  `allow_clash_bookings` tinyint(1) NOT NULL DEFAULT 0,
  `capacity` int(11) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL,
```

- [ ] **Step 3: Review schema diff**

Run:

```bash
git diff -- sql/initial.sql updates/26.1.alpha1/resourceBooking/01-addResourceCapacityField.sql
```

Expected: the only schema change is the `resource.capacity` column addition.

- [ ] **Step 4: Commit database storage**

Run:

```bash
git add sql/initial.sql updates/26.1.alpha1/resourceBooking/01-addResourceCapacityField.sql
git commit -m "Add resource capacity storage [SA-21790]"
```

Expected: one local commit is created.

## Task 2: Backend Model, Mapping, And Server Validation

**Files:**
- Modify: `library/Schoolbox/ResourceBooking/Item/Model.php`
- Modify: `library/Schoolbox/ResourceBooking/Item/Mapper.php`
- Modify: `library/Schoolbox/ResourceBooking/Item/Controller.php`

- [ ] **Step 1: Add model property and accessors**

In `Model.php`, add this property after `allowClashBookings`:

```php
    protected int $capacity = 1;
```

Add these methods after `setAllowClashBookings()`:

```php
    public function getCapacity(): int
    {
        return $this->capacity;
    }

    public function setCapacity(int $capacity): self
    {
        $this->capacity = $capacity;

        return $this;
    }
```

Add capacity to `jsonSerialize()` after `bookingIsAuthorized`:

```php
            'capacity'             => $this->getCapacity(),
```

- [ ] **Step 2: Map database column**

In `Mapper.php`, add the mapper field immediately after `allow_clash_bookings`:

```php
        $this->uses('capacity', self::TYPE_INTEGER);
```

- [ ] **Step 3: Parse and validate POST data**

In `Controller.php`, update the model chain in `updateItemWithFormValues()` from:

```php
            ->setCurrentTermRestriction((bool)($post['current_term_restriction'] ?? false))
            ->setAllowClashBookings((bool)($post['allow_clash_bookings'] ?? false));
```

to:

```php
            ->setCurrentTermRestriction((bool)($post['current_term_restriction'] ?? false))
            ->setAllowClashBookings((bool)($post['allow_clash_bookings'] ?? false))
            ->setCapacity($this->getCapacityFromForm($post['capacity'] ?? null));
```

Add this helper after `updateItemWithFormValues()`:

```php
    protected function getCapacityFromForm(mixed $capacity): int
    {
        $validatedCapacity = filter_var(
            $capacity,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 1,
                    'max_range' => 999,
                ],
            ]
        );

        if ($validatedCapacity === false) {
            throw ErrorMapper::getInstance()->generateException(
                ErrorMapper::ERROR_INFORMATION_MISSING,
                null,
                'Capacity of resource must be a whole number from 1 to 999.'
            );
        }

        return $validatedCapacity;
    }
```

- [ ] **Step 4: Run focused PHP checks**

Run:

```bash
./vendor/bin/phpcs library/Schoolbox/ResourceBooking/Item/Model.php library/Schoolbox/ResourceBooking/Item/Mapper.php library/Schoolbox/ResourceBooking/Item/Controller.php
./vendor/bin/phpstan analyze library/Schoolbox/ResourceBooking/Item/Model.php library/Schoolbox/ResourceBooking/Item/Mapper.php library/Schoolbox/ResourceBooking/Item/Controller.php
```

Expected: no new PHPCS or PHPStan failures.

- [ ] **Step 5: Commit backend support**

Run:

```bash
git add library/Schoolbox/ResourceBooking/Item/Model.php library/Schoolbox/ResourceBooking/Item/Mapper.php library/Schoolbox/ResourceBooking/Item/Controller.php
git commit -m "Add resource capacity backend support [SA-21790]"
```

Expected: one local commit is created.

## Task 3: Create/Edit Form And Payload Defaults

**Files:**
- Modify: `library/Schoolbox/ResourceBooking/Item/templates/edit.twig`
- Modify: `test/codeception/Support/Payload/ResourceBooking/Resource/Create.php`
- Modify: `test/codeception/Support/Payload/ResourceBooking/Resource/Update.php`

- [ ] **Step 1: Add client-side form validation**

In `edit.twig`, add this `capacity` rule after `resource_category_id`:

```javascript
                    capacity: {
                        digits: true,
                        range: [1,999]
                    },
```

Add this message after `resource_category_id`:

```javascript
                    capacity:                       "Capacity of resource must be a whole number from 1 to 999.",
```

- [ ] **Step 2: Add the capacity input below the conflict toggle**

In the `Allow Conflicting Booking Requests` fieldset in `edit.twig`, keep the switch inside a `small-12 columns` wrapper and add this sibling directly after it:

```twig
                    <div class="small-4 columns">
                        <label for="capacity">
                            Capacity of resource
                            <input name="capacity" id="capacity" type="number" min="1" max="999" step="1" value="{{ item.capacity|default(1) }}" required />
                        </label>
                    </div>
```

The fieldset should read:

```twig
                <fieldset class="content">
                    <legend>
                        Allow Conflicting Booking Requests
                        <span class="hint has-tip tip-top" data-tooltip title="If toggled to ON, users can request a booking where the time is already taken. Only one booking can be an approved booking for a given time.">?</span>
                    </legend>
                    <div class="small-12 columns">
                        <div class="switch">
                            <input type="checkbox" value="1" {% if item.allowClashBookings %}checked{% endif %} id="allow_clash_bookings" name="allow_clash_bookings">
                            <label for="allow_clash_bookings">
                                <span>On</span>
                                <span>Off</span>
                            </label>
                        </div>
                    </div>
                    <div class="small-4 columns">
                        <label for="capacity">
                            Capacity of resource
                            <input name="capacity" id="capacity" type="number" min="1" max="999" step="1" value="{{ item.capacity|default(1) }}" required />
                        </label>
                    </div>
                </fieldset>
```

- [ ] **Step 3: Default create payload capacity**

In `Create.php`, add this to `__construct()` after `setResourceCat('1')`:

```php
        $this->setCapacity('1');
```

Add the setter after `setAllowClashBookings()`:

```php
    public function setCapacity(string $capacity): void
    {
        $this->setFormParam('capacity', $capacity);
    }
```

- [ ] **Step 4: Default update payload capacity**

In `Update.php`, add this to `__construct()` after `setResourceCat('1')`:

```php
        $this->setCapacity('1');
```

Add the setter after `setMaxResTime()`:

```php
    public function setCapacity(string $capacity): void
    {
        $this->setFormParam('capacity', $capacity);
    }
```

- [ ] **Step 5: Run focused PHP style checks**

Run:

```bash
./vendor/bin/phpcs test/codeception/Support/Payload/ResourceBooking/Resource/Create.php test/codeception/Support/Payload/ResourceBooking/Resource/Update.php
```

Expected: no new PHPCS failures in the payload files.

- [ ] **Step 6: Commit form and payload support**

Run:

```bash
git add library/Schoolbox/ResourceBooking/Item/templates/edit.twig test/codeception/Support/Payload/ResourceBooking/Resource/Create.php test/codeception/Support/Payload/ResourceBooking/Resource/Update.php
git commit -m "Add resource capacity form field [SA-21790]"
```

Expected: one local commit is created.

## Task 4: Smoke Test Coverage

**Files:**
- Modify: `test/codeception/Smoketests/ResourceBookingCest.php`

- [ ] **Step 1: Assert create default capacity**

In `createResource()`, replace:

```php
        $newItemID = $ItemMapper->findOneByColumn('name', $resource_title)->getID();
```

with:

```php
        $newItem = $ItemMapper->findOneByColumn('name', $resource_title);
        $newItemID = $newItem->getID();
```

Add this assertion after the redirect assertion:

```php
        $i->assertEquals(1, $newItem->getCapacity(), 'Default resource capacity is 1');
```

- [ ] **Step 2: Assert update capacity persists**

In `updateResource()`, add this before sending the update request:

```php
        $updatePayload->setCapacity('7');
```

After the redirect assertion, reload the resource and assert:

```php
        $updatedItem = $ItemMapper->findById($newItemID);
        $i->assertEquals(7, $updatedItem->getCapacity(), 'Updated resource capacity is persisted');
```

- [ ] **Step 3: Assert invalid capacities are rejected**

Add this Cest method after `updateResource()`:

```php
    public function createResourceRejectsInvalidCapacity(IntegrationTester $i): void
    {
        foreach (['0', '1000', 'abc', '1.5', ''] as $capacity) {
            $resource = new Payload\ResourceBooking\Resource\Create();
            $resource->setResourceName('Testing Resource Invalid Capacity at ' . microtime(true));
            $resource->setCapacity($capacity);

            $i->sendRequest($resource);

            $i->seeResponseCodeIs(400);
        }
    }
```

- [ ] **Step 4: Run focused smoke tests**

Run:

```bash
./vendor/bin/codecept run Smoketests ResourceBookingCest:createResource
./vendor/bin/codecept run Smoketests ResourceBookingCest:updateResource
./vendor/bin/codecept run Smoketests ResourceBookingCest:createResourceRejectsInvalidCapacity
```

Expected: all three tests pass.

- [ ] **Step 5: Commit smoke coverage**

Run:

```bash
git add test/codeception/Smoketests/ResourceBookingCest.php
git commit -m "Test resource capacity create and update [SA-21790]"
```

Expected: one local commit is created.

## Task 5: Final Verification

**Files:**
- No file edits.

- [ ] **Step 1: Run relevant backend checks**

Run:

```bash
./vendor/bin/phpcs library/Schoolbox/ResourceBooking/Item/Model.php library/Schoolbox/ResourceBooking/Item/Mapper.php library/Schoolbox/ResourceBooking/Item/Controller.php test/codeception/Support/Payload/ResourceBooking/Resource/Create.php test/codeception/Support/Payload/ResourceBooking/Resource/Update.php test/codeception/Smoketests/ResourceBookingCest.php
./vendor/bin/phpstan analyze library/Schoolbox/ResourceBooking/Item/Model.php library/Schoolbox/ResourceBooking/Item/Mapper.php library/Schoolbox/ResourceBooking/Item/Controller.php
./vendor/bin/codecept run Smoketests ResourceBookingCest:createResource
./vendor/bin/codecept run Smoketests ResourceBookingCest:updateResource
./vendor/bin/codecept run Smoketests ResourceBookingCest:createResourceRejectsInvalidCapacity
```

Expected: all relevant checks pass.

- [ ] **Step 2: Confirm no conflict-capacity logic was added**

Run:

```bash
git diff origin/develop...HEAD -- library/Schoolbox/ResourceBooking/Booking library/Schoolbox/ResourceBooking/Booking/Validation.php
```

Expected: no changes are shown for booking conflict logic files from this work.

- [ ] **Step 3: Confirm branch status**

Run:

```bash
git status --short --branch
git log --oneline --max-count=8
```

Expected: the SA-21790 local commits are present, there are no unexpected tracked changes, and no push has been performed.

## Self-Review

- Spec coverage: schema, default/backfill, model/mapper read support, create/update POST support, form field placement, test payload defaults, validation bounds, and smoke tests are covered.
- Placeholder scan: no placeholder implementation steps remain.
- Type consistency: the field is named `capacity` in SQL, mapper, model accessors, POST payloads, Twig input, JSON output, and tests.
