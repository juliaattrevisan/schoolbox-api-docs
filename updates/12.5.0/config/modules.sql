/* Boarder Leave module boolean
 */
ALTER TABLE `config` CHANGE `type` `type` ENUM('Site','Security','Policy','Paths','Resources','Email','Welcome','Localisation','External DB','Colours','Board Leave','Modules') CHARSET utf8 COLLATE utf8_general_ci NULL;
INSERT INTO `config`(`configID`,`name`,`value`,`title`,`description`,`field`,`options`,`type`,`editable`,`hidden`,`required`) VALUES ( NULL,'boarder_leave_enabled','0','Enable Boarder Leave Module','Enables boarder leave module and connectivity to Event Attendance','boolean','true:false','Modules','false','false','false');

/* Mobile Module */
INSERT INTO `config`(`configID`,`name`,`value`,`title`,`description`,`field`,`options`,`type`,`editable`,`hidden`,`required`) VALUES ( NULL,'mobile_enabled','0','Enable Mobile Module','Enables mobile edition of Schoolbox for phones and tablets.','boolean','true:false','Modules','false','false','false');