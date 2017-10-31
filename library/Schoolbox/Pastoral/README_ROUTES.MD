# Pastoral Care Route Summary

[Back](./README.MD)

| Route | Description | Roles |
| --- | --- | --- |
| **GET ROUTES** | | |
| / | Pastoral care dashboard page | Staff Only |
| /search | Pastoral care record search page. This is intentionally kept separate from the core Schoolbox search functionality | Staff Only |
| /student/{studentId} | Students pastoral care record. Displays a paginated list of all pastoral care records for the student | Staff & Parents |
| /student/{studentId}/record/{recordId} | Individual pastoral record detail page | Staff & Parents |
| /student/{studentId}/linked/{linkedStudentId} | Shows all records that {studentId} and {linkedStudentID} have in common | Staff Only |
| /student/{studentId}/record/{recordId}/attachment/{attachmentId} | Download URL for pastoral document attachments | Staff & Parents |
| /student/{studentId}/record/insert | Create new pastoral record page | Staff |  
| /student/{studentId}/record/{recordId}/update | Edit pastoral record page | Staff |
| /pastoral/management/groups | Dashboard for viewing/modifying/creating pastoral groups | Pastoral Moderators Only
| /pastoral/management/groups/modify/{groupId} | Create/edit pastoral group page | Pastoral Moderators Only 
| /pastoral/report | Pastoral report builder dashboard | Staff Only |
| /pastoral/report/insert | Create new pastoral report page | Staff Only |
| /pastoral/report/update/{reportId} | Modify existing pastoral report page | Staff Only |
| /pastoral/report/results/{reportId} | Run pastoral report and view results | Staff Only |
| /pastoral/report/results/tag/{tagId} | Display all pastoral records that have the requested tag | Staff Only |
| /pastoral/report/results/{reportId}/print | Printable report view (Deprecated) | Staff Only |
| **POST ROUTES**|   |   |
| /record/insert | Save new pastoral record | Staff Only |
| /record/{recordId}/update | Save updates to existing pastoral record | Staff Only |
| /record/{recordId}/delete | Delete existing pastoral record | Staff Only |
| /record/{recordId|/comment/insert | Save new pastoral comment | Staff Only |
| /record/{recordId|/comment/{commentId}/delete | Delete existing pastoral comment | Staff Only |
| /pastoral/management/groups/delete | Delete existing pastoral group | Pastoral Moderators Only 
| /pastoral/management/groups/modify | Save updates to existing pastoral group | Pastoral Moderators Only
| /pastoral/report/modify | Save updates/Create new pastoral report | Staff Only |
| /pastoral/report/delete/{reportId} | Delete a saved pastoral report | Staff Only |
| /pastoral/report/dashboard/modify | Updates whether a report appears on the staff members pastoral dashboard | Staff Only |
