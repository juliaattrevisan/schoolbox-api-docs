/* Create table for aggregated group homepage hits */
CREATE TABLE `homepage_visits`(
	`date` date NOT NULL  ,
	`user_group_id` int(10) unsigned NOT NULL  ,
	`category_id` int(10) unsigned NOT NULL  ,
	`hitcount` int(10) unsigned NULL  DEFAULT 1 ,
	PRIMARY KEY (`date`,`user_group_id`,`category_id`)
) ENGINE=MyISAM DEFAULT CHARSET='utf8';