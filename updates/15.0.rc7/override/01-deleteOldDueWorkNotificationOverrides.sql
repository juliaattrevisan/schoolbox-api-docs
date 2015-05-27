/**
 * msg_reroute_override was populated based on rows in submission_notifications.
 *
 * These overrides should not be needed anymore.
 */
DELETE mr FROM `msg_reroute_override` mr
INNER JOIN submission_notification sn
    ON sn.`box_id` = REPLACE(REPLACE(REPLACE(REPLACE(msg_route_key, 'duework.', ''), '.returned', ''), '.upcoming', ''), '.overdue', '')
WHERE mr.msg_route_key RLIKE '^duework\.[[:digit:]]+\.(returned|upcoming|overdue)'
AND mr.user_id = 0;
