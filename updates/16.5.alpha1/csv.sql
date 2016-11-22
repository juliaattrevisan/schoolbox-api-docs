INSERT IGNORE INTO `allowedFileTypes`
(
  `description`,
  `mime`,
  `iconFile`,
  `inline`,
  `enable`)
VALUES
  (
    'CSV Spreadsheet',
    'text/csv',
    'application-table.svg',
    'false',
    'true'
  );

INSERT IGNORE INTO allowedFileExtension (fileTypeID, extension) SELECT allowedFileTypes.fileTypeID, 'csv' FROM allowedFileTypes WHERE allowedFileTypes.mime = 'text/csv';

UPDATE allowedFileExtension SET allowedFileExtension.fileTypeID = (SELECT allowedFileTypes.fileTypeID FROM allowedFileTypes WHERE mime ='text/csv') WHERE extension = 'csv';
