UPDATE config
SET title = 'Allow staff to post to global audiences'
WHERE name = 'news_staff_post_enabled';

UPDATE config
SET title = 'Allow modification of news posted by others to a homepage'
WHERE name = 'news_edit_other';
