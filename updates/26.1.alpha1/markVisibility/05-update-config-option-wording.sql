-- The wording of this config option needs to change because 'grade visibility' is now called 'feedback visibility',
-- and this setting applies to other activity types besides due work and quizzes.
--
-- Previous wording:
-- MariaDB [schoolbox]> select name, title, description from config where title='Default Visibility of Graded Due Work'\G
-- *************************** 1. row ***************************
--        name: submission_access
--       title: Default Visibility of Graded Due Work
-- description: Select the default visibility of grades and feedback on due work and quizzes. This setting respects the 'Default Visibility of Graded Due Work' setting.
-- 1 row in set (0.001 sec)
UPDATE config SET
title = 'Default Feedback Visibility',
description = 'Select the default visibility of grades and comments when creating a new activity. Visibility may also be affected by activity audiences set on the work type.'
WHERE name = 'submission_access';
