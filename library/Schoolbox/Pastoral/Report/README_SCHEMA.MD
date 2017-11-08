# Table Structure

[Back](./README.MD)

## pastoral_import_log

| column | description |
|--- | --- |
| id | Unique row identifier |
| owner_id | The ID of the report author |
| name | Name of the pastoral report|
| description | Detailed free-form text description of the report |
| before_date | This is the date/time which pastoral record must be created before | 
| after_date | This is the date/time which pastoral record must be created after |
| severity_id | This is the ID of the minimum severity level to include in the reprt | 
| involved | If the user that created the report is a superuser- they can see every record in the system. Ticking this box will limit the results to only those records in which they are involved- this is the default behaviour for non-superusers | 
| types | This is a CSV string of pastoral care record types to be included in the report | 
| authors | This is a CSV string of authors user IDs to be included in the report |
| students | This is a CSV string of student user IDs to be included in the report |
| tags | This is a CSV string of tag IDs to be included in the record
| order | This is a CSV string that is plonked straight into the ORDER BY section of the SQL query | 
| years | This is a CSV string containing the Schoolbox year levels to be included in the record |
| created_at | The date/time the time the pastoral report was created |
| updated_at| The date/time the time the pastoral report was last updated |
| deleted_at | The date/time the time the pastoral report was deleted |



