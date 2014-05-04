/* Moved lu_config_type changes here since they need to run first */

/* SB-4438 New LMS Policy area */
INSERT INTO `lu_config_type` (`type`) VALUES ('LMS Policy');

/* SB-4437 Securit config tab cleanup */
INSERT INTO `lu_config_type` (`type`) VALUES ('LDAP');
INSERT INTO `lu_config_type` (`type`) VALUES ('Proxy');
