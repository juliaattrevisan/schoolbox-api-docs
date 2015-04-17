/* Make integer fields nullable, and add SETs */
ALTER TABLE `msg_route_perm`
	CHANGE `min_level` `min_level` int(10) unsigned   NULL after `role_id` ,
	CHANGE `max_level` `max_level` int(10) unsigned   NULL after `min_level` ,
	CHANGE `default_level` `default_level` int(10) unsigned   NULL after `max_level` ,
	ADD COLUMN `mask` set('MU','DI','IN','MO','EM')  COLLATE utf8_bin NULL after `default_level` ,
	ADD COLUMN `role_default` set('MU','DI','IN','MO','EM')  COLLATE utf8_bin NULL after `mask` ;


/* Make integer fields nullable, and add SET */
ALTER TABLE `msg_reroute`
  CHANGE `outgoing_level` `outgoing_level` INT(10) UNSIGNED NULL,
  ADD COLUMN `delivery` SET('MU','DI','IN','MO','EM') NULL AFTER `outgoing_level`;
