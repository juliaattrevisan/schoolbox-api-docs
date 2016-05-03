ALTER TABLE `msg_route_perm`
  DROP COLUMN `min_level`,
  DROP COLUMN `max_level`,
  DROP COLUMN `default_level`;

ALTER TABLE `msg_reroute`
  DROP COLUMN `outgoing_level`;

ALTER TABLE `msg_reroute_override`
  DROP COLUMN `outgoing_level`;
