# External SIS Database

The External SIS database is one variant of the SIS database.

This is a direct connection to the SIS' database: the data within that database
is not managed by Schoolbox in any way, and is writable by Schoolbox only in
[rare cases](#writing).

External SIS database direct connections are only available for on-premise
Schoolbox instances: cloud instances are not able to connect to a school's
database directly, and thus must use the [School User Data Store](suds.md). For
this reason, it is expected that direct connections will become less important
over time.

## Implementations

Implementations exist for the following SIS providers:

### Core

These implementations are the most widely used, roughly in order of usage:

- Synergetic
- Schoolbox (a.k.a. the [School User Data Store](suds.md))
- TASS (deprecated, being replaced with a SUDS variant)
- PCSchool
- Schoolpro


### Fringe

These implementations are used by only a small number of schools, but are still
supported:

- DoubleFirst
- Edumate
- iSAMS (deprecated, replaced with a SUDS variant)
- Schooledge

## Writing

Schoolbox generally does not allow itself to modify data within an external SIS
database; however, the following exceptions to the rule exist:
- Markbook synchronisation
- Attendance marking
- Pastoral care
- Document upload
- Community portal password update

These exceptions exist _only_ for the Synergetic implementation.
