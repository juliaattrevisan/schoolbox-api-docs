# Table Structure

[Back](./README.MD)

## pastoral

| column | description |
|--- | --- |
| id | Unique row ID |
| parent_id | The ID of the parent record (if this was a duplicate record creating using the 'Linked Students' feature) |
| owner_id | The ID of the author of the record |
| severity_id | The ID of the severity associated with the record from the 'lu_pastoral_severity' table |
| student_id | The ID of the student this record related to |
| type_id | The ID of the pastoral type associated with the record from the 'lu_pastoral_type' table |
| subtype_id |The ID of the pastoral subtype (if any) associated with the record from the 'lu_pastoral_subtype' table |
| external_id | The external SIS ID of this record if it was imported/exported to the SIS |
| private | Deprecated flag indicating the record is private. This is ignored from V17.0 onwards | 
| heading | The title of the pastoral record |
| body_private | The private content for the pastoral record |
| body_public | The public content for the pastoral record |
| points | The number of points associated with the pastoral record |
| modified_by_user_id | The ID of the user that modified the record |
| created_at | The date the pastoral record was originally created |
| updated_at | The date the pastoral record was updated |
| deleted_at | The date the pastoral record was deleted |