-- This is to remove userAccess created improperly for class folders
-- Create back up first
CREATE TABLE _backup_userGroups_v21_1_9 AS
(SELECT uid, sid, service, userAccess.type, user.created_at
FROM userAccess
         JOIN folder ON userAccess.sid = folder.id AND folder.deleted_at IS NULL
         LEFT JOIN userGroups ON userGroups.cid = userAccess.sid -- this is null for non group folder
         JOIN folder_code ON folder_code.folder_id = folder.id AND folder_code.deleted_at IS NULL
         JOIN user ON user.id = uid AND user.deleted_at IS NULL
WHERE userGroups.force_follow IS NULL
  AND user.created_at > '2022-03-10'); -- release date of 21.1.9
-- Delete the entries above
DELETE userAccess
FROM userAccess
         JOIN folder ON userAccess.sid = folder.id AND folder.deleted_at IS NULL
         LEFT JOIN userGroups ON userGroups.cid = userAccess.sid
         JOIN folder_code ON folder_code.folder_id = folder.id AND folder_code.deleted_at IS NULL
         JOIN user ON user.id = uid AND user.deleted_at IS NULL
WHERE userGroups.force_follow IS NULL
  AND user.created_at > '2022-03-10';
