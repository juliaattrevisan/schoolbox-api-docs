UPDATE msg_namespace
SET label = '{:eotc|p|c}'
WHERE slug = 'eotc';

UPDATE msg_source ms
SET ms.description = 'someone submits {:eotc|indef} {:eotc}'
WHERE ms.description = 'someone submits an EOTC';

UPDATE msg_source ms
SET ms.description = 'a moderator changes the status of your {:eotc}'
WHERE ms.description = 'a moderator changes the status of your EOTC';

UPDATE msg_source ms
SET ms.description = 'someone updates or comments on your {:eotc}'
WHERE ms.description = 'someone updates or comments on your EOTC';

UPDATE msg_source ms
SET ms.description = '{:eotc|indef} {:eotc} is upcoming and requires your approval'
WHERE ms.description = 'an EOTC is upcoming and requires your approval';
