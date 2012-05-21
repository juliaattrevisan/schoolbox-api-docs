/* Created new config for setting the default news expiry */

INSERT INTO `config`
(`configID`,`name`,`value`,`title`,`description`,`field`,`options`,`type`,`editable`,`hidden`,`required`)
VALUES
( NULL, 'news_default_expiry','+1 week','Default News expiry date','The relative date that news will expire on by default. Can be any string like \'tomorrow\', \'+1 week\', \'next Thursday\'','input',NULL,'Resources','true','false','true');