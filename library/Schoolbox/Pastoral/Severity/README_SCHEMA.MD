# Table Structure

[Back](./README.MD)

## lu_pastoral_severity

| column | description |
|--- | --- |
| id | Unique row identifier |
| name | Name of the severity (e.g. Low, Medium, High, Super High) |
| order | Number on which results are sorted to determine display order of severities |
| external_code| The external SIS code for the severity (used when exporting records) |
| _external_id | Looks like this was an erroneously created column |
| deleted_at | Date/Time the severity was deleted | 
