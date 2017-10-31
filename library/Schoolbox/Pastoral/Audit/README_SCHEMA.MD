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
| search_result | If this field is populated, and the 'report_id' field is populated, this indicates the results that were displayed to the user as a result of running the report.<br><br>If this field is populated, and the 'search' field is populated, this indicates the results that were displayed to the user as a result of running the search.<br><br>If this field is blank and either of the fields are populated, this indicates the search or report returned no results. | 
| created_at | The date/time the time the pastoral access occurred |
