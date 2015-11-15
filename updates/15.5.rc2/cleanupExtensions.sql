/*This query will delete filetypes that have no extensions*/

DELETE allowedFileTypes FROM allowedFileTypes LEFT JOIN allowedFileExtension ON allowedFileTypes.fileTypeID = allowedFileExtension.fileTypeID
WHERE allowedFileExtension.`fileTypeID` IS NULL;


/*Cleanup extensions that have no type*/
DELETE allowedFileExtension FROM allowedFileExtension LEFT JOIN allowedFileTypes ON allowedFileTypes.fileTypeID = allowedFileExtension.fileTypeID
WHERE allowedFileTypes.`fileTypeID` IS NULL;