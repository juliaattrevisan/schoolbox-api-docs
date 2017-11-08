# Table Structure

[Back](./README.MD)

## lu_pastoral_type

| column | description |
|--- | --- |
| id | Unique row identifier |
| pastoral_type_id | The ID of the pastoral type to which this subtype is a child |
| pastoral_document_classification_id | The ID of the external document classification the type should be linked to. Refer to [External Document Classification](../DocumentClassification/README.MD) documentation |
| name | Name of the subtype |
| external_code| The external SIS code for this pastoral type (used when exporting) |
| _external_id | Looks like this was an erroneously created column | 
