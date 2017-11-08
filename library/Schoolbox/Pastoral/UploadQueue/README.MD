# Pastoral Document Upload Queue

[Back](../README.MD)

## Links

[Schema Documentation](./README_SCHEMA.MD)  

## TLDR

This is a list of files to be uploaded from OTFS into Synergetic Docman. The uploading of these files is done by a
CRON job whose implementation is buried in `External_Synergetic_Pastoral::uploadPastoralDocument()`

## Where In Synergetic

The location of Docman is slightly different depending on the schema version the school is using

### V67 (or earlier)

SynergyOneMedia.dbo.tDocuments  
SynergyOneMedia.dbo.DocumentReferences

### V68 

SynergyOne.media.tDocuments  
SynergyOne.media.DocumentReferences

### tDocuments

This contains the actual file data. The file is stored as a hex encoded string in the `document` field.

### DocumentReferences

This contains the links between the file in `tDocuments` and the ID of the person in Synergetic it relates to
(student records- and their ID are rooted in Synergetics `Community` table)