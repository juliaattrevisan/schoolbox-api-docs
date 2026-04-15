# V261 Mark Visibility Parser — Design Spec

**Date:** 2026-04-15
**Ticket:** SA-22007
**Author:** tomcdonnell

## Background

SA-22007 changes the `mark_visibility` DB column from an integer to a string enum
(`'private'`, `'student'`, `'parentStudent'`, `'parent'`). The assessment export XML
template (`templates/core/assess/exportToMoodle.xml`) writes `{ASSESS_MARK_VISIBILITY}`
directly from the DB row, so post-migration exports will contain string values in the
`<markvisibility>` field instead of integers.

The existing parsers (V160 through V170) handle the `<markvisibility>` field by casting
it to `int` and mapping the integer to a string constant. This is correct for old exports
but **wrong for new exports**: casting a string like `'student'` to `int` yields `0`,
which maps to `MARK_VISIBILITY_PRIVATE` — silently losing the original visibility.

The export template currently stamps `<version>17.0</version>`. The import dispatcher
(`Assess_Import_Xml::getParser()`) selects a parser based on this version string. If we
update the version stamp without adding a matching parser, imports will throw
`DomainException`.

## Goal

Ensure that assessments exported from Schoolbox 26.1+ can be re-imported with the
correct `mark_visibility` value intact.

## Design

### Approach

Add a new versioned parser `V261` that returns the `<markvisibility>` string directly,
with no integer conversion. Bump the export template version to `26.1` so new exports
use this parser path. Old exports (version ≤ 17.0) continue to use the existing parsers.

No changes are needed to the Survey parser hierarchy — surveys do not have a
`markvisibility` field.

### Files Changed

**1. `library/Evaluation/Import/Parser/IParser.php`**

Add constant:
```php
const FORMAT_VERSION_261 = '26.1';
```

**2. `library/Assess/Import/Parser/V261.php`** *(new file)*

```php
namespace Assess\Import\Parser;

class V261 extends V170
{
    public function getMarkVisibility(): string
    {
        return (string) $this->getXml()->markvisibility;
    }
}
```

Extends V170 (the current top of the chain). Only overrides `getMarkVisibility()`.
All other getters are inherited unchanged.

No defensive validation — this parser is only invoked for files exported by 26.1+
Schoolbox instances, which always write valid `IAssessable` constants. Validation at
this internal boundary would add complexity without safety benefit.

**3. `library/Assess/Import/Xml.php`**

Add a case to `getParser()`:
```php
case IParser::FORMAT_VERSION_261:
    return new \Assess\Import\Parser\V261($xml);
```

**4. `templates/core/assess/exportToMoodle.xml`**

Bump the version field:
```xml
<version>26.1</version>
```

### Data Flow

```
Export (26.1+):  DB string → XML <markvisibility>student</markvisibility>  + <version>26.1</version>
Import (26.1+):  version=26.1 → V261::getMarkVisibility() → (string) → 'student'  ✓

Export (<26.1):  DB int → XML <markvisibility>1</markvisibility>  + <version>17.0</version>
Import (any):    version=17.0 → V160::getMarkVisibility() → match(1) → 'student'  ✓
```

### Out of Scope

- Cross-version import (26.1 export → pre-26.1 Schoolbox) is not supported by the
  existing architecture and is not addressed here.
- The Survey import parser hierarchy requires no changes.
