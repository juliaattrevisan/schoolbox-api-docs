# School User Data Store

The School User Data Store (SUDS) is one variant of the SIS database.

This variant actually writes its data to tables within Schoolbox's database
itself: Schoolbox will query a remote API which the SIS provides, and use the
response from that API to write data into Schoolbox itself.

It evolved out of the Schoolbox-branded SIS database connector, which was built
for schools which either do not have a SIS at all or who use a SIS which
Schoolbox was unable to connect to.

## Implementations

Implementations exist for the following SIS providers:

- iSAMS
- Kamar
- Sentral
- TASS
- Compass
- Wonde
- LISS

In addition, schools may choose to upload CSV files directly.
