UPDATE msg_source
SET description = REPLACE(description, 'your child\'s calendar', 'your calendar')
WHERE description LIKE 'an event on your child\'s calendar%'