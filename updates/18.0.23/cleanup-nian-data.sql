/* No groups when nian is enabled */
DELETE userAccess FROM userAccess JOIN folder ON userAccess.service = 1 AND userAccess.sid = folder.id WHERE folder.inherit = 1;
DELETE userGroups FROM userGroups JOIN folder ON cid = folder.id WHERE folder.inherit = 1;

/* No role access when nian is enabled */
DELETE perms FROM perms JOIN folder ON cid = folder.id WHERE folder.inherit = 1;

/* No class codes when nian is enabled */
DELETE folder_code FROM folder_code JOIN folder ON folder_code.folder_id = folder.id WHERE folder.inherit = 1;