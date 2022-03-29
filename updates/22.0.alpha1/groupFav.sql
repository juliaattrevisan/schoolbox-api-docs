alter table userAccess
    add favourite bool default false null;

UPDATE menu_top
SET url = '/groups'
WHERE url = '/resource/mygroups';

UPDATE menu_side
SET url = '/groups'
WHERE url = '/group/join';

###update all group entries to be favourite by default during the upgrade
UPDATE userAccess SET favourite = true WHERE service = 1;
###if a user has set a mute on a group, remove that from the favourite list
UPDATE userAccess
    join msg_reroute_override on msg_reroute_override.user_id=userAccess.uid
    AND userAccess.sid = SUBSTRING_INDEX(msg_route_key,'.',-1)
    SET favourite = false
WHERE userAccess.service = 1 AND msg_reroute_override.delivery = 'MU';
