

/* Alter table in First database */
alter table `intranet`.`homepage_comp_location` 
	change `component` `component` int(11) unsigned   NOT NULL DEFAULT '0' after `column`, 
	drop column `size`, COMMENT='';
/* Create table in First database */

create table `intranet`.`poll`(
	`pollID` int(11) NOT NULL  auto_increment  , 
	`categoryID` int(11) NULL   , 
	`title` varchar(120) COLLATE latin1_swedish_ci NULL   , 
	`question` varchar(250) COLLATE latin1_swedish_ci NULL   , 
	`option_0` varchar(120) COLLATE latin1_swedish_ci NULL   , 
	`option_1` varchar(120) COLLATE latin1_swedish_ci NULL   , 
	`option_2` varchar(120) COLLATE latin1_swedish_ci NULL   , 
	`option_3` varchar(120) COLLATE latin1_swedish_ci NULL   , 
	`option_4` varchar(120) COLLATE latin1_swedish_ci NULL   , 
	`option_5` varchar(120) COLLATE latin1_swedish_ci NULL   , 
	`option_6` varchar(120) COLLATE latin1_swedish_ci NULL   , 
	`option_7` varchar(120) COLLATE latin1_swedish_ci NULL   , 
	`option_8` varchar(120) COLLATE latin1_swedish_ci NULL   , 
	`option_9` varchar(120) COLLATE latin1_swedish_ci NULL   , 
	`result_0` int(11) NOT NULL   , 
	`result_1` int(11) NOT NULL   , 
	`result_2` int(11) NOT NULL   , 
	`result_3` int(11) NOT NULL   , 
	`result_4` int(11) NOT NULL   , 
	`result_5` int(11) NOT NULL   , 
	`result_6` int(11) NOT NULL   , 
	`result_7` int(11) NOT NULL   , 
	`result_8` int(11) NOT NULL   , 
	`result_9` int(11) NOT NULL   , 
	`frequency` enum('once','hourly','daily') COLLATE latin1_swedish_ci NOT NULL  DEFAULT 'once'  , 
	`created` datetime NULL   , 
	`close` datetime NULL   , 
	PRIMARY KEY (`pollID`) , 
	KEY `categoryID`(`categoryID`) 
)Engine=MyISAM DEFAULT CHARSET='latin1';

/* Create table in First database */

create table `intranet`.`pollLog`(
	`pollID` int(11) NULL   , 
	`userID` int(11) NULL   , 
	`created` datetime NULL   , 
	KEY `pollID`(`pollID`,`userID`) 
)Engine=MyISAM DEFAULT CHARSET='latin1';

insert into `homepage_comp` (`id`, `size`, `method`, `name`, `description`, `filename`, `config`, `invisible`) values('33','2','outputPoll','Poll','Create a poll to determine group members opinion on a given subject.','poll.htm','a:0:{}','false');