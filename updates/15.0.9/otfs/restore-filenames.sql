UPDATE `file`
SET `realname` = `description`
WHERE `realname` = '';

UPDATE `myFile`
SET `realname` = `description`
WHERE `realname` = '';

UPDATE `dropbox`
SET `realname` = `description`
WHERE `realname` = '';

UPDATE `student_submission`
SET `realname` = `hash`
WHERE `realname` = '';

/* Please note submission_return is absent: due to the way the table is used, we cannot guess filenames here, it must be restored from backup */

UPDATE `storage`
SET `filename` = `hash`
WHERE `filename` = '';
