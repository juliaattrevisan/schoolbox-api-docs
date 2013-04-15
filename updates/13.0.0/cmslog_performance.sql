/* add missing index to improve performance of log statistics queries [SB-2177]*/
ALTER TABLE `cmsLog` ADD INDEX `objectId` (`objectId` DESC) ;
