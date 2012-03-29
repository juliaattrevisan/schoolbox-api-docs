/* Create tables */
CREATE TABLE `submission_grade`(
	`grade_id` int(11) NOT NULL  auto_increment ,
	`lower_bound` int(11) NOT NULL  ,
	`caption` VARCHAR(64) COLLATE utf8_general_ci NOT NULL  ,
	PRIMARY KEY (`grade_id`)
) ENGINE=MyISAM DEFAULT CHARSET='utf8';

INSERT INTO `submission_grade`(`grade_id`,`lower_bound`,`caption`)
    VALUES (1,0,'Fail'),(2,50,'Pass'),(3,60,'Credit'),(4,70,'Distinction'),(5,80,'High Distinction');

CREATE TABLE `submission_mark_type`(
	`mark_type_id` int(11) NOT NULL  auto_increment ,
	`hr_name` varchar(32) COLLATE utf8_general_ci NOT NULL  ,
	`slug` varchar(16) COLLATE utf8_general_ci NOT NULL  ,
	`comment` varchar(255) COLLATE utf8_general_ci NOT NULL  ,
	`enabled` int(1) NULL  DEFAULT '1' ,
	PRIMARY KEY (`mark_type_id`) ,
	UNIQUE KEY `slug`(`slug`)
) ENGINE=MyISAM DEFAULT CHARSET='utf8';

INSERT INTO `submission_mark_type`(`mark_type_id`,`hr_name`,`slug`,`comment`,`enabled`)
    VALUES (1,'Percentage (&#37;)','percent','This should be a whole percentage between 0 and 100.',1),
           (2,'Letter Grade','letter','Select a letter grade from the list.',1),
           (3,'Total Mark','raw','This should be a mark between 0 and {MAX_MARK}. It may be a whole number or a decimal.',1),
           (4,'Free Type (legacy support)','free','Should be a letter (A), score (15/20), or percentage (88%).',0);


/* Alter tables */
ALTER TABLE `student_submission`
	CHANGE `submitted` `submitted` datetime   NULL after `realname`;

ALTER TABLE `submission_return`
	CHANGE `mark` `mark` varchar(64)  COLLATE utf8_general_ci NULL after `comment`,
	ADD COLUMN `norm_mark` decimal(14,11)   NULL after `mark`,
	CHANGE `filename` `filename` varchar(80)  COLLATE utf8_general_ci NULL after `norm_mark`,
	CHANGE `deleted` `deleted` tinyint(1) unsigned   NOT NULL DEFAULT '0' after `filename`,
	CHANGE `submitted` `submitted` datetime   NULL after `deleted`,
	CHANGE `returned` `returned` datetime   NOT NULL after `submitted`;

ALTER TABLE `submission_box`
	ADD COLUMN `mark_type` int(11)   NULL after `created`,
	ADD COLUMN `max_mark` int(11)   NULL after `mark_type`;


/* Update tables */
UPDATE submission_box SET mark_type = '4';