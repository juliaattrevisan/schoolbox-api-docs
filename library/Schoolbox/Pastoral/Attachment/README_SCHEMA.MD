# Table Structure
 
[Back](./README.MD)
 
## pastoral_attachment

| column | description |
|--- | --- |
| id | Unique row identifier |
| pastoral_id | Unique row identifier of the record in 'pastoral' table this relates |
| hash | This is the Schoolbox/OTFS storage hash of the file | 
| created_at | The date/time the record was created |
| updated_at | This is totally redundant and should probably be removed |
| deleted_at | The date/time the record was deleted. This will happen in the course of updating a record and adding/removing permissions. This should be kept as a primitive audit trail of permission changes over time. |
| private | If set to '1' this indicates that the file is considered to be part of the pastoral records 'Private Content'<br><br>If set to '0' this indicates that the file is considered to be part of the pastoral records 'Public Content' |
