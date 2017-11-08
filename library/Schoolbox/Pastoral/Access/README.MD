# Pastoral Care Record Visiblity

[Back](../README.MD)

## Links

[Schema Documentation](./README_SCHEMA.MD)  
[Visibility Logic PlantUML](./Diagrams/access.puml)

## TLDR
 
Permissions are slightly complicated to understand, but in a nutshell:

* Pastoral moderators can at a minimum read everything (but not necessarily modify/delete)
* If a user is not listed in a records audience, and did not author the record, they will never see it in the system
* If there is private content on a record, only audience members with the 'Enable Private Content' checkbox ticked 
  will see it
  
[Pastoral Care Record Visibility](./README_VISIBILITY.MD)  
[Pastoral Care Private/Public Content](./README_VISIBILITY.MD)  
