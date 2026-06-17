---
name: uuid-usage
description: Use when generating an identifier which may be shared with external systems, and when deciding upon how to generate and store that identifier
---

# UUID Usage

Universally unique identifiers (UUIDs), a.k.a. globally unique identifiers (GUIDs) may be used as an alternative unique identifier to database-generated auto-incrementing values.

UUIDs may be used as primary keys, but are generally used as an alternative identifier.

## When to use UUIDs?

Consider using UUIDs when:
- the identifier is expected to be shared with external systems, not just used internally within Schoolbox

## Which UUID to use?

Prefer v7 UUIDs: these increment monotonically whilst retaining a random component, thus making them more suitable for use within indexed database columns.

v4 UUIDs (completely random values) are acceptable where they are already in use.

## How to generate UUIDs

### Within mappers

The constants below are defined in `\Schoolbox\Mapper`, which all mappers subclass. A column may be auto-populated with a UUID by defining it like so in the mapper's `mapping` method:
```php
$this->uses($columnName, self::TYPE_STRING, self::COLUMN_AUTOGENERATE_UUID);
```

If the column is to be the primary key of the table, use this instead:
```php
$this->uses($columnName, self::TYPE_STRING, self::COLUMN_IDENTIFIER_UUID);
```

Either method will cause the column to be given a v7 UUID value on save, if it does not already have a value.

The database column `$columnName` should be a MariaDB `UUID` column: whilst UUIDs are generally displayed as 36-character strings, they are more efficiently stored as 128-bit numeric values.

### Within arbitrary code

Use the `ramsey/uuid` library:

```php
// Generate a v7 UUID: preferred for new code
\Ramsey\Uuid\Uuid::uuid7();

// Generate a v4 UUID: only when already in use
\Ramsey\Uuid\Uuid::uuid4();
```
