/**
 * msg_reroute_override was populated based on rows in submission_notifications.
 *
 * These overrides should not be needed anymore.
 */
DELETE FROM `msg_reroute_override`
WHERE msg_route_key RLIKE '^duework\.[[:digit:]]+\.(returned|upcoming|overdue)'
AND user_id = 0;
