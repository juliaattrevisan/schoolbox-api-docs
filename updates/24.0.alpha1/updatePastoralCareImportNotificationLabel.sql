-- Was "{:pastoralCare} Import Notification"
-- but caused "Pastoral Care Import Notification Notifications" to be displayed in the UI
UPDATE msg_namespace
SET
    label = "{:pastoralCare} Import"
WHERE
    slug = "pastoral_log";
