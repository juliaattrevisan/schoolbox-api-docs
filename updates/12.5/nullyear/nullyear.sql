/* At some point, someone made the year column NOT NULL and it's not in any sql update files?
 * They did it in both stable and dev
 */
ALTER TABLE `user` CHANGE `year` `year` VARCHAR(3) CHARSET utf8 COLLATE utf8_general_ci NULL;