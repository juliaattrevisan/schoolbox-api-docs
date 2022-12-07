# Mappers

Mappers are Schoolbox's implementation of an Object-Relational Mapper: they
allow create/read/update/delete actions on a database using objects which
represent a single row in a database table.

## Implementations

In practice, all mappers extend the `\Schoolbox\Mapper` base class: each
sub-class represents a mapping onto a single database table.

This class provides the following core methods:

- `find` methods, for finding records
- `count` methods, for fetching a count of records which match a set
- `save`, for creating new and updating existing records
- `delete`, for deleting already created records
