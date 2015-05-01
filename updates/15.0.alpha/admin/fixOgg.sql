UPDATE allowedFileExtension SET fileTypeID = (SELECT fileTypeID FROM allowedFileTypes WHERE mime = 'audio/ogg') WHERE extension = 'ogg';

UPDATE allowedFileTypes SET description = 'OGG Multiplexed' WHERE mime = 'application/ogg';
