/* Make integer fields nullable, and add SETs */
ALTER TABLE `msg_route_perm`
	CHANGE `min_level` `min_level` int(10) unsigned   NULL after `role_id` ,
	CHANGE `max_level` `max_level` int(10) unsigned   NULL after `min_level` ,
	CHANGE `default_level` `default_level` int(10) unsigned   NULL after `max_level` ,
	ADD COLUMN `MASK` set('MU','DI','IN','MO','EM')  COLLATE utf8_bin NULL after `default_level` ,
	ADD COLUMN `DEF` set('MU','DI','IN','MO','EM')  COLLATE utf8_bin NULL after `MASK` ;