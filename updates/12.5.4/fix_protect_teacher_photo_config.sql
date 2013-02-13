-- Some schools have this config description set to something to do with Plagscan.
-- This should fix that.
UPDATE `config`
SET `description`='Tick this box to prevent teacher photos being displayed to student and parents in search results.'
WHERE `name`='protect_teacher_photo'
LIMIT 1;
