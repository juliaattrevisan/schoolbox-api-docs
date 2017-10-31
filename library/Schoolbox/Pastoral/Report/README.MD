# Pastoral Care Report Builder

[Back](../README.MD)

## Links

[Schema Documentation](./README_SCHEMA.MD)  
[Pastoral Report Dashboard](./Dashboard/README.MD)

## TLDR

This mapper/model is reponsible for the storage of pastoral report definitions.

## Report Structure

The report builder (aka. "SQL Injection Builder") is fairly primitive. It allows the user to create reports using
the following criteria:

- Don't show records before
- Don't show records after
- Don't show records below a severity level
- Don't show records I am not involved in (pastoral moderators only)
- Filter by pastoral record type
- Filter by pastoral record author
- Filter by student
- Filter by pastoral tags
- Filter by student year levels
- Order by any of these fields

To facilitate this the report definition simply contains comma delimited strings/ints that are injected into a fairly 
basic SQL query in the `\Schoolbox\Pastoral\Mapper::getReportData()` function.