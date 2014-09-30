INSERT INTO `allowedFileTypes` (`description`, `mime`, `iconFile`, `inline`, `enable`) VALUES
('Matroska Audio','audio/x-matroska','sound.png','false','true');

INSERT INTO `allowedFileExtension` (`fileTypeID`, `extension`) SELECT `allowedFileTypes`.`fileTypeID`,'mka' FROM `allowedFileTypes` WHERE mime like 'audio/x-matroska';
