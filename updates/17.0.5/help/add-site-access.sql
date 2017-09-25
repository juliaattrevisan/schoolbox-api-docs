CREATE TABLE `site_access`(
  `site_school_code` VARCHAR(32) NOT NULL,
  `access_token` VARCHAR(40) NOT NULL,
  PRIMARY KEY (`site_school_code`)
) CHARSET=ASCII COLLATE=ascii_general_ci;
