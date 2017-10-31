# Table Structure

[Back](./README.MD)

## pastoral_import_log

| column | description |
|--- | --- |
| id | Unique row identifier |
| pastoral_id | The ID of the pastoral record after it was imported to Schoolbox |
| import_id | The ID of the pastoral import. Each pastoral import request is assigned a unique ID, this ID is then recorded against each action taken as part of the import. |
| user_id | This is the schoolbox user ID of the person that initiated the import |
| student_name | This is the name of the student the record relates to |
| creator_name | This is the name of the author of the pastoral record |
| heading | This is the heading of the pastoral record as it was imported |
| result | This is the result of this records import. If it failed this will contain the reason why |
| created_at | The date/time the time the record was imported |

