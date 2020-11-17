-- Converts textarea comments' \n char to <br />

UPDATE pastoral_comment
SET body = REPLACE(body, '\n', '<br />');
