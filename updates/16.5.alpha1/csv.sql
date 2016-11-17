INSERT INTO `schoolbox`.`allowedFileTypes`
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

UPDATE allowedFileExtension SET allowedFileExtension.fileTypeID = (SELECT allowedFileTypes.fileTypeID FROM allowedFileTypes WHERE mime ='text/csv') WHERE extension = 'csv'