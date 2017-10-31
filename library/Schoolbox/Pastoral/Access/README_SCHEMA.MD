# Table Structure

[Back](./README.MD)

## pastoral_access

| column | description |
|--- | --- |
| id | Unique row identifier |
| pastoral_id | Unique row identifier of the record in 'pastoral' table this relates |
| group_id | If set, this is the unique row identifier of a pastoral care group as found in the 'pastoral_group' table.  This indicates the record is granting all users in the pastoral care group access to the pastoral record.<br><br>If NULL, this indicates the record does not relate to a pastoral group.
| role_id| If set, this is the unique row identifier of a system role (e.g. staff, student, parent) as found in the illogically named 'groups' table. This indicates the record is granting all users in that role to the pastoral record.<br><br>If NULL, this indicates the record does not relate to a pastoral group.
| user_id | If set, this is the unique row identifier of an individual user 'user' table. This indicates the record is granting this single user access to the pastoral record.<br><br>If NULL, this indicates the record does not relate to a pastoral group.
| created_at | The date/time the record was created |
| deleted_at | The date/time the record was deleted. This will happen in the course of updating a record and adding/removing permissions. This should be kept as a primitive audit trail of permission changes over time. |
| private | If this value is set to '1' then depending on which of the group_id/role_id/user_id fields is populated, the relevant group, role or individual user will be granted access to both the private content and public content associated with the pastoral record.<br><br>If this value is set to '0' then the same entites will only be granted access to the public content associated with the pastoral record.
