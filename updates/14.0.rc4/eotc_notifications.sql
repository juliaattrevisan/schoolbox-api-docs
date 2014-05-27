INSERT INTO `msg_namespace` VALUES (9, 'eotc', 'EOTC');

INSERT INTO `msg_source`
  (`namespace`, `key`, `role_type`, `description`)
VALUES
  (9, 'submitted',     'staff', 'someone submits an EOTC'),
  (9, 'statuschanged', 'staff', 'a moderator changes the status of your EOTC'),
  (9, 'updated',       'staff', 'someone updates or coments on your EOTC');
