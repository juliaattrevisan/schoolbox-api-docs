# Table Structure

[Back](./README.MD)

## lu_pastoral_type

| column                                           | description                                                        |
|--------------------------------------------------|--------------------------------------------------------------------|
| id                                               | Unique row identifier                                              |
| pastoral_document_classification_id              | See note below                                                     |
| pastoral_document_classification_confidential_id | See note below                                                     |
| name                                             | Name of the type                                                   |
| template                                         | Template for general comments on records with no subtype           |
| color                                            |                                                                    |
| icon                                             |                                                                    |
| external_code                                    | The external SIS code for this pastoral type (used when exporting) |
| visibility_locked                                | Whether users may modify visibility of records of this type        |
| sentiment                                        | Positive, neutral, or negative (enumerated values)                 |
| sequence                                         | Integer defining the display order                                 |
| updated_by                                       | User ID of most recent updater                                     |
| created_at                                       |                                                                    |
| updated_at                                       |                                                                    |
| deleted_at                                       |                                                                    |
| template_private                                 | Template for confidential comments on records with no subtype      |

Notes:
 * The pastoral_document_classification_ids point to names that match classification names in Synergetic.  These determine
   which types of Synergetic users have access to content exported from Schoolbox.  The confidential id applies to the
   parts of pastoral records labelled as 'confidential' in Schoolbox.
   Refer to [External Document Classification](../DocumentClassification/README.MD) documentation.

## lu_pastoral_subtype

| column            | description                                                           |
|-------------------|-----------------------------------------------------------------------|
| id                | Unique row identifier                                                 |
| pastoral_type_id  | The ID of the pastoral type to which this subtype is a child          |
| name              | Name of the subtype                                                   |
| template          | Template for general comments on records with this subtype            |
| color             |                                                                       |
| icon              |                                                                       |
| external_code     | The external SIS code for this pastoral subtype (used when exporting) |
| visibility_locked | Whether users may modify visibility of records of this subtype        |
| sentiment         | Positive, neutral, or negative (enumerated values)                    |
| sequence          | Integer defining the display order                                    |
| updated_by        | User ID of most recent updater                                        |
| created_at        |                                                                       |
| updated_at        |                                                                       |
| deleted_at        |                                                                       |
| template_private  | Tempalte for confidential comments on records with no subtype         |
