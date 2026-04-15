# V261 Mark Visibility Parser Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a V261 import parser that reads `mark_visibility` as a string from XML exports, and bump the export template to version `26.1`.

**Architecture:** Extend the existing versioned parser chain (V160 → V165 → V170) with a new V261 class that overrides only `getMarkVisibility()` to return the XML value as a string directly (no integer mapping). Update the export template version stamp and the import dispatcher to wire the new version in.

**Tech Stack:** PHP 8, PHPUnit 12, SimpleXMLElement

---

## File Map

| Action | File | Purpose |
|--------|------|---------|
| Modify | `library/Evaluation/Import/Parser/IParser.php` | Add `FORMAT_VERSION_261 = '26.1'` constant |
| Create | `library/Assess/Import/Parser/V261.php` | New parser; overrides `getMarkVisibility()` to return string directly |
| Create | `library/Assess/Import/Parser/Test/V261Test.php` | Unit tests for V261 |
| Modify | `library/Assess/Import/Xml.php` | Add `FORMAT_VERSION_261` case to `getParser()` switch |
| Modify | `templates/core/assess/exportToMoodle.xml` | Bump `<version>` from `17.0` to `26.1` |

---

## Task 1: Add the FORMAT_VERSION_261 constant

**Files:**
- Modify: `library/Evaluation/Import/Parser/IParser.php:24-26`

- [ ] **Step 1: Add the constant**

Open `library/Evaluation/Import/Parser/IParser.php`. The current constants are:

```php
const FORMAT_VERSION_160 = '16.0';
const FORMAT_VERSION_165 = '16.5';
const FORMAT_VERSION_170 = '17.0';
```

Add the new constant after the existing three:

```php
const FORMAT_VERSION_160 = '16.0';
const FORMAT_VERSION_165 = '16.5';
const FORMAT_VERSION_170 = '17.0';
const FORMAT_VERSION_261 = '26.1';
```

- [ ] **Step 2: Commit**

```bash
git add library/Evaluation/Import/Parser/IParser.php
git commit -m "Add FORMAT_VERSION_261 constant [SA-22007]"
```

---

## Task 2: Create the V261 parser with TDD

**Files:**
- Create: `library/Assess/Import/Parser/Test/V261Test.php`
- Create: `library/Assess/Import/Parser/V261.php`

- [ ] **Step 1: Write the failing test**

Create `library/Assess/Import/Parser/Test/V261Test.php`:

```php
<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Assess\Import\Parser\Test;

use Assess\Import\Parser\V261;
use PHPUnit\Framework\TestCase;
use Schoolbox\Learning\Assessment\IAssessable;

class V261Test extends TestCase
{
    private function makeParser(string $markVisibility): V261
    {
        $xml = new \SimpleXMLElement(
            "<quiz><markvisibility>{$markVisibility}</markvisibility></quiz>"
        );
        return new V261($xml);
    }

    public function testGetMarkVisibilityReturnsPrivate(): void
    {
        $parser = $this->makeParser(IAssessable::MARK_VISIBILITY_PRIVATE);
        $this->assertSame(IAssessable::MARK_VISIBILITY_PRIVATE, $parser->getMarkVisibility());
    }

    public function testGetMarkVisibilityReturnsStudent(): void
    {
        $parser = $this->makeParser(IAssessable::MARK_VISIBILITY_STUDENT);
        $this->assertSame(IAssessable::MARK_VISIBILITY_STUDENT, $parser->getMarkVisibility());
    }

    public function testGetMarkVisibilityReturnsParent(): void
    {
        $parser = $this->makeParser(IAssessable::MARK_VISIBILITY_PARENT);
        $this->assertSame(IAssessable::MARK_VISIBILITY_PARENT, $parser->getMarkVisibility());
    }

    public function testGetMarkVisibilityReturnsParentStudent(): void
    {
        $parser = $this->makeParser(IAssessable::MARK_VISIBILITY_PARENT_STUDENT);
        $this->assertSame(IAssessable::MARK_VISIBILITY_PARENT_STUDENT, $parser->getMarkVisibility());
    }
}
```

- [ ] **Step 2: Run the test to confirm it fails**

```bash
vendor/bin/phpunit library/Assess/Import/Parser/Test/V261Test.php --bootstrap test/bootstrap/unit.php
```

Expected: 4 errors — `Class "Assess\Import\Parser\V261" not found`

- [ ] **Step 3: Create the V261 parser**

Create `library/Assess/Import/Parser/V261.php`:

```php
<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Assess\Import\Parser;

/**
 * v26.1 quiz variations — mark_visibility is now exported as a string.
 *
 * Prior versions exported mark_visibility as an integer; V160 maps those integers
 * to string constants. From 26.1 onwards the value is already a string constant,
 * so no mapping is required.
 */
class V261 extends V170
{
    /**
     * @return string
     */
    public function getMarkVisibility(): string
    {
        return (string) $this->getXml()->markvisibility;
    }
}
```

- [ ] **Step 4: Run the test to confirm it passes**

```bash
vendor/bin/phpunit library/Assess/Import/Parser/Test/V261Test.php --bootstrap test/bootstrap/unit.php
```

Expected:
```
OK (4 tests, 4 assertions)
```

- [ ] **Step 5: Commit**

```bash
git add library/Assess/Import/Parser/V261.php library/Assess/Import/Parser/Test/V261Test.php
git commit -m "Add V261 parser with string mark_visibility passthrough [SA-22007]"
```

---

## Task 3: Wire up the dispatcher and bump the export version

**Files:**
- Modify: `library/Assess/Import/Xml.php:111-121`
- Modify: `templates/core/assess/exportToMoodle.xml:3`

- [ ] **Step 1: Add the V261 case to the import dispatcher**

Open `library/Assess/Import/Xml.php`. The `getParser()` method currently reads:

```php
switch ($fileFormatVersion) {
    case IParser::FORMAT_VERSION_160:
        return new \Assess\Import\Parser\V160($xml);
    case IParser::FORMAT_VERSION_165:
        return new \Assess\Import\Parser\V165($xml);
    case IParser::FORMAT_VERSION_170:
        return new \Assess\Import\Parser\V170($xml);
    default:
        throw new DomainException('No parser found for version: ' . $fileFormatVersion);
}
```

Add the new case before `default`:

```php
switch ($fileFormatVersion) {
    case IParser::FORMAT_VERSION_160:
        return new \Assess\Import\Parser\V160($xml);
    case IParser::FORMAT_VERSION_165:
        return new \Assess\Import\Parser\V165($xml);
    case IParser::FORMAT_VERSION_170:
        return new \Assess\Import\Parser\V170($xml);
    case IParser::FORMAT_VERSION_261:
        return new \Assess\Import\Parser\V261($xml);
    default:
        throw new DomainException('No parser found for version: ' . $fileFormatVersion);
}
```

- [ ] **Step 2: Bump the export template version**

Open `templates/core/assess/exportToMoodle.xml`. Line 3 currently reads:

```xml
<version>17.0</version>
```

Change it to:

```xml
<version>26.1</version>
```

- [ ] **Step 3: Run the full test suite to confirm no regressions**

```bash
vendor/bin/phpunit --bootstrap test/bootstrap/unit.php
```

Expected: all tests pass (same count as before this PR, plus the 4 new V261 tests).

- [ ] **Step 4: Commit**

```bash
git add library/Assess/Import/Xml.php templates/core/assess/exportToMoodle.xml
git commit -m "Wire V261 parser into dispatcher and bump export version to 26.1 [SA-22007]"
```
