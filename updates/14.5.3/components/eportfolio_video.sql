/* Add MyFile video type
 */
ALTER TABLE `video_queue` CHANGE `type` `type` ENUM('sbfs','storage','submission','myfile') CHARSET utf8 COLLATE utf8_general_ci NOT NULL;