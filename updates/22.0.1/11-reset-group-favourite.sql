-- Update all group entries to be favourite by default during the upgrade
UPDATE userAccess
SET favourite = true
WHERE service = 1;

-- If a user has set a mute on a group, remove that from the favourite list
UPDATE userAccess
    JOIN msg_reroute_override
    ON msg_reroute_override.user_id = userAccess.uid
        AND userAccess.sid = SUBSTRING_INDEX(msg_route_key, '.', -1)
SET favourite = false
WHERE userAccess.service = 1
  AND msg_reroute_override.delivery = 'MU';

-- If message is disabled on a group, remove that from favourite lists
UPDATE userAccess
    JOIN msg_reroute_override
    ON msg_reroute_override.user_id = 0
        AND userAccess.sid = SUBSTRING_INDEX(msg_route_key, '.', -1)
SET favourite = false
WHERE userAccess.service = 1
  AND msg_reroute_override.delivery = 'MU';
