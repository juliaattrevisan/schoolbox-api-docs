# Pastoral Record Tags 

[Back](../README.MD)

## Links

[Schema Documentation](./README_SCHEMA.MD)  

## TLDR

Tags are just free-form labels that can be associated with pastoral records as the end users see fit. Pastoral tags
are entered as free-form text by the user, and the backend code stores these with a unique ID in 'lu_pastoral_tags'
and de-duplicates them as best possible. Selectize will display any already entered tags while the users types to
type and prompt them. 

Likewise, when tags are removed from records and no references exist, their tag record in 'lu_pastoral_tags' is removed 
and they will no longer appear in selectize.

The implementation of this was done by Steve Chan.