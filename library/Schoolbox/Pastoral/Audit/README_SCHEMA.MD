# Table Structure

[Back](./README.MD)

## pastoral_audit

| column | description |
|--- | --- |
| id | Unique row identifier |
| user_id | Unique row identifier of the user in 'user' table this audit record related to |
| student_id | If this field is populated this indicates that the user was visiting this students pastoral page |
| report_id | If this field is populated this indicates that the user was running the report from 'report 'table with this ID |
| student_id | If this field is populated this indicates that the user was visiting the specific pastoral record from the 'pastoral' table with this ID |
| search | If this field is populated it indicated that the user did a pastoral search for this term. The results of the search (if any) are shown in the 'search_result' column |
| created_at | The date/time the time the pastoral access occurred |
