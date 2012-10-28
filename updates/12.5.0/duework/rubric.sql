-- Additional marktypes
INSERT INTO `submission_mark_type`
  (`hr_name`, `slug`, `comment`)
VALUES
  ('Rubric', 'rubric', 'To mark the Rubric you <b>must</b> select one result for each criterion. Simply click the squares to select the results.'),
  ('None', 'none', 'This due work is not marked.');

-- NEW TABLES!
CREATE TABLE `rubric_criterion`(
	`id` int(11) NOT NULL  auto_increment ,
	`grouping` varchar(64) COLLATE latin1_swedish_ci NOT NULL  ,
	`name` varchar(128) COLLATE latin1_swedish_ci NOT NULL  ,
	`parent_id` int(11) NOT NULL  ,
	`parent_type` enum('submission','category','curriculum') COLLATE latin1_swedish_ci NOT NULL  DEFAULT 'category' ,
	PRIMARY KEY (`id`) ,
	UNIQUE INDEX `grouping` (`grouping`, `name`, `parent_id`, `parent_type`)
) ENGINE=MyISAM DEFAULT CHARSET='utf8';

CREATE TABLE `rubric_descriptor`(
	`criterion_id` int(10) unsigned NOT NULL  ,
	`submission_box_id` int(10) unsigned NOT NULL  ,
	`value` int(10) unsigned NOT NULL  ,
	`description` varchar(127) COLLATE latin1_swedish_ci NULL  ,
	PRIMARY KEY (`criterion_id`,`value`,`submission_box_id`)
) ENGINE=MyISAM DEFAULT CHARSET='utf8';

CREATE TABLE `rubric_mark`(
	`student_id` int(10) unsigned NOT NULL  ,
	`submission_box_id` int(10) unsigned NOT NULL  ,
	`criterion_id` int(10) unsigned NOT NULL  ,
	`tstamp` timestamp NOT NULL  DEFAULT CURRENT_TIMESTAMP ,
	`mark` int(10) unsigned NOT NULL  ,
	`valid` tinyint(1) NULL  DEFAULT 1 ,
	PRIMARY KEY (`student_id`,`submission_box_id`,`criterion_id`,`tstamp`)
) ENGINE=MyISAM DEFAULT CHARSET='utf8';

CREATE TABLE `rubric_result`(
	`resultset_id` int(11) NOT NULL  ,
	`value` int(11) NOT NULL  ,
	`label` varchar(32) COLLATE latin1_swedish_ci NOT NULL  ,
	PRIMARY KEY (`resultset_id`,`value`)
) ENGINE=MyISAM DEFAULT CHARSET='utf8';

CREATE TABLE `rubric_usage`(
	`criterion_id` int(11) NOT NULL  ,
	`submission_box_id` int(11) NOT NULL  ,
	PRIMARY KEY (`criterion_id`,`submission_box_id`)
) ENGINE=MyISAM DEFAULT CHARSET='utf8';

-- Add rubric resultset field to submission box table
ALTER TABLE `submission_box`
	ADD COLUMN `rubric_result_set` int(10) unsigned   NOT NULL DEFAULT 0 after `max_mark`;

-- Upgrade comments to fulltext (might be redundant)
ALTER TABLE `submission_return`
	CHANGE `comment` `comment` text  COLLATE utf8_general_ci NULL after `owner`;

