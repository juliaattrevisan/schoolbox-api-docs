This namespace exists because there are two implementations of user relations
depending on which version of PCSchool an instance is connecting to. We have the
means to detect the difference with
```sql
SELECT COUNT(*)
FROM pcschool.dbo.workarea
WHERE DBNAME = 'PCSchool'
AND STATUS = 'F'
```

This will tell us whether the database is using the FREEDOM module or not:
* If it is, for all user relations we need to use the `ALUMREL` table
* If not, we need to use the `FAMILY` table

The classes in this namespace are intended for composited use by the main
interfaced classes in order to execute the correct queries.