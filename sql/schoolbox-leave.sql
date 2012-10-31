/* Create leave table */
CREATE TABLE `leave_request`(
	`id` int(11) NOT NULL  auto_increment ,
	`user_id` int(11) NOT NULL  ,
	`user_phone` varchar(255) COLLATE latin1_swedish_ci NOT NULL  ,
	`start` datetime NOT NULL  ,
	`finish` datetime NOT NULL  ,
	`host_type` int(11) NOT NULL  ,
	`host_name` varchar(255) COLLATE latin1_swedish_ci NOT NULL  ,
	`host_phone` varchar(255) COLLATE latin1_swedish_ci NOT NULL  ,
	`description` varchar(255) COLLATE latin1_swedish_ci NOT NULL  ,
	`created` datetime NULL  ,
	`status` int(11) NOT NULL  ,
	`authorisation_datetime` datetime NULL  ,
	`authorisation_user_id` int(11) NULL  ,
	`notes` text COLLATE latin1_swedish_ci NULL  ,
	`confirmed_by` int(11) NULL  ,
	PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET='latin1';
