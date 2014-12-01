UPDATE msg_source
SET description = REPLACE(description, 'comments after you', 'posts')
WHERE namespace = 4;

