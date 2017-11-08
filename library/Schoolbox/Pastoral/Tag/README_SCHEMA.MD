# Table Structure

[Back](./README.MD)

## pastoral_tag

This table links the pastoral tag to the pastoral record.

| column | description |
|--- | --- |
| id | Unique row identifier |
| pastoral_id | The ID of the pastoral record the tag is associated with |
| tag_id | The ID of the pastoral tag |
| created_at | The date the pastoral tag was added |
| deleted_at | The date the pastoral tag was removed |

## lu_pastoral_tag

This table defines the tags, this is where the autocomplete in selectize comes from.

| column | description |
|--- | --- |
| id | Unique row identifier |
| name | Name of the pastoral tag |
| created_at | The date the pastoral tag was created |
| created_at | The date the pastoral tag was last updated |
| deleted_at | The date the pastoral tag was deleted |


