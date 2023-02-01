# Schoolbox Database

The Schoolbox Database is a standard MariaDB instance.

Each schema within this instance contains the configuration and data for a
single Schoolbox instance.

Communication with the database may be done via two methods:
- [mappers](../adapters/mappers.md)
- direct queries, for when the mapper's overhead is not required or desirable
