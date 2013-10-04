/* Missing index for cmsLog queries SB-3241 */
ALTER TABLE `cmsLog` ADD INDEX `time` (`time` ASC);
