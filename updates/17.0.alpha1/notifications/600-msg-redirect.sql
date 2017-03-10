CREATE TABLE `msg_redirect`(
  `from` VARCHAR(512) NOT NULL,
  `to` VARCHAR(512) NOT NULL
) CHARSET=ASCII COLLATE=ascii_bin
COMMENT='Table is ascii on purpose, route data is always ascii.';

ALTER TABLE `schoolbox`.`msg_redirect`
  ADD INDEX (`from`),
  ADD INDEX (`to`),
  ADD INDEX (`from`, `to`);
