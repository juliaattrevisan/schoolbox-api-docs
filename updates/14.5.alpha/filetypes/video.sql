INSERT INTO `allowedFileTypes` (`description`, `mime`, `iconFile`, `inline`, `enable`) VALUES
('Matroska Video','video/x-matroska','video_alt.png','false','true');

INSERT INTO `allowedFileTypes` (`description`, `mime`, `iconFile`, `inline`, `enable`) VALUES
('DivX','video/x-divx','video_alt.png','false','true');


INSERT INTO `allowedFileExtension` (`fileTypeID`, `extension`) SELECT `allowedFileTypes`.`fileTypeID`,'mkv' FROM `allowedFileTypes` WHERE mime like 'video/x-matroska';

INSERT INTO `allowedFileExtension` (`fileTypeID`, `extension`) SELECT `allowedFileTypes`.`fileTypeID`,'divx' FROM `allowedFileTypes` WHERE mime like 'video/x-divx';