UPDATE `error`
SET `message` = 'The size of this file exceeds the allowed limit. %MESSAGE%'
WHERE `message` = 'The size of this file exceeds the allowed limit.';