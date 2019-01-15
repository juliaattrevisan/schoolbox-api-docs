UPDATE `msg_source`
  SET `description` = 'somebody replied to your forum post or thread'
  WHERE `namespace` = (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'forum')
    AND `key` = 'mentioned';
UPDATE `msg_source`
  SET `description` = 'somebody posts a thread'
  WHERE `namespace` = (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'forum')
    AND `key` = 'followed';
