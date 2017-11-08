# Table Structure

[Back](./README.MD)

## pastoral_comment

| column | description |
|--- | --- |
| id | Unique row identifier |
| pastoral_id | Unique row identifier of the record in 'pastoral' table this relates |
| owner_id | Unique row identifier of the author of the comment in the 'user' table  |
| body | The comment |
| private | If set to '1' this indicates that the comment is considered to be part of the pastoral records 'Private Content'<br><br>If set to '0' this indicates that the comment is considered to be part of the pastoral records 'Public Content' |
| external_code | If pastoral external SIS synchronization is enabled, this will contain the external SIS record ID of the comment |
| created_at | The date/time the time the pastoral record was created |
| updated_at| The date/time the time the pastoral record was last updated |
| deleted_at | The date/time the time the pastoral record was deleted |
