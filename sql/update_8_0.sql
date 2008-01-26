
/* Create table in Second database */

create table `intranet`.`cache`(
	`cache_id` int(10) unsigned NOT NULL  auto_increment  , 
	`user_id` int(10) unsigned NOT NULL  DEFAULT '0'  , 
	`session_id` varchar(40) COLLATE latin1_swedish_ci NULL   , 
	`cache_key` varchar(128) COLLATE latin1_swedish_ci NOT NULL   , 
	`created` datetime NOT NULL  DEFAULT '0000-00-00 00:00:00'  , 
	`data` longtext COLLATE latin1_swedish_ci NOT NULL   , 
	PRIMARY KEY (`cache_id`) , 
	KEY `user_id`(`user_id`) , 
	KEY `cache_key`(`cache_key`) , 
	KEY `session_id`(`session_id`) 
)Engine=MyISAM DEFAULT CHARSET='latin1';


/* Alter table in Second database */
alter table `intranet`.`homepage_comp` 
	add column `description` text  COLLATE latin1_swedish_ci NULL after `name`, 
	change `filename` `filename` varchar(22)  COLLATE latin1_swedish_ci NULL after `description`, 
	add column `config` text  COLLATE latin1_swedish_ci NOT NULL after `filename`, COMMENT='';

/* Alter table in Second database */
alter table `intranet`.`homepage_comp_location` 
	add column `config` text  COLLATE latin1_swedish_ci NOT NULL after `component`, 
	drop column `size`, COMMENT='';
/* Create table in Second database */

create table `intranet`.`messages`(
	`message_id` int(11) unsigned NOT NULL  auto_increment  , 
	`user_id` int(11) unsigned NOT NULL  DEFAULT '0'  , 
	`del` tinyint(1) NOT NULL  DEFAULT '0'  , 
	`cache_key` varchar(128) COLLATE latin1_swedish_ci NOT NULL   , 
	`created` datetime NOT NULL  DEFAULT '0000-00-00 00:00:00'  , 
	`idx` int(11) unsigned NOT NULL  DEFAULT '0'  , 
	`uid` int(11) unsigned NOT NULL  DEFAULT '0'  , 
	`subject` varchar(255) COLLATE latin1_swedish_ci NOT NULL   , 
	`from` varchar(255) COLLATE latin1_swedish_ci NOT NULL   , 
	`to` varchar(255) COLLATE latin1_swedish_ci NOT NULL   , 
	`cc` varchar(255) COLLATE latin1_swedish_ci NOT NULL   , 
	`date` datetime NOT NULL  DEFAULT '0000-00-00 00:00:00'  , 
	`size` int(11) unsigned NOT NULL  DEFAULT '0'  , 
	`headers` text COLLATE latin1_swedish_ci NOT NULL   , 
	`structure` text COLLATE latin1_swedish_ci NULL   , 
	PRIMARY KEY (`message_id`) , 
	UNIQUE KEY `uniqueness`(`user_id`,`cache_key`,`uid`) , 
	KEY `user_id`(`user_id`) , 
	KEY `idx`(`idx`) , 
	KEY `uid`(`uid`) 
)Engine=MyISAM DEFAULT CHARSET='latin1';


/* Alter table in Second database */
alter table `intranet`.`submission_box` 
	add column `type` int(11)   NULL after `category`, 
	change `owner` `owner` int(4) unsigned   NOT NULL DEFAULT '0' after `type`, COMMENT='';


insert into `homepage_comp` (`size`, `method`, `name`, `description`, `filename`, `config`) values('1','outputRSS','RSS Feed','Display external RSS site headlines. The RSS feed can be from any site that provides a feed.','rss_n.htm','a:2:{s:3:\"url\";s:0:\"\";s:4:\"size\";i:5;}');
insert into `homepage_comp` (`size`, `method`, `name`, `description`, `filename`, `config`) values('2','outputRSS','Small RSS Feed','Display external RSS site headlines and brief description. The RSS feed can be from any site that provides a feed.','rss_s.htm','a:2:{s:3:\"url\";s:0:\"\";s:4:\"size\";i:5;}');
insert into `homepage_comp` (`size`, `method`, `name`, `description`, `filename`, `config`) values('1','outputDueWorkList','Due Work','Output all due work for this homepage. This allows students to see due dates and submit via the homepage.','duework_n.htm','a:1:{s:4:\"size\";i:0;}');
insert into `homepage_comp` (`size`, `method`, `name`, `description`, `filename`, `config`) values('0','outputDueWorkList','Small Due Work','Shows a list of all due work and its due date.','duework_s.htm','a:1:{s:4:\"size\";i:0;}');