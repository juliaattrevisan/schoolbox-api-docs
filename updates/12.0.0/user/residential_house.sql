/* not all databases contain the residential_house_id field on user...!! */
ALTER TABLE `user`
ADD COLUMN `residential_house_id` int(11) NULL after `house`;

/* Databases are also lacking the residential house table... */
CREATE TABLE `luResidentialHouse`(
	`id` int(20) NOT NULL  auto_increment ,
	`name` varchar(255) COLLATE latin1_swedish_ci NULL  ,
	PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET='latin1';