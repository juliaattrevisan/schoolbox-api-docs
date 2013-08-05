-- Any allowed file types without a matching extension should be removed, so
-- they don't block saving of new extensions.
-- (types without extension don't show in the allowed file admin page)
DELETE `allowedFileTypes` FROM `allowedFileTypes`
LEFT JOIN `allowedFileExtension` ON `allowedFileTypes`.`fileTypeID`=`allowedFileExtension`.`fileTypeID`
WHERE `allowedFileExtension`.`fileTypeID` IS NULL
