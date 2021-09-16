#---update the following error messages from saying category to folder
UPDATE error t
SET t.message = 'You do not have write permission to this folder.'
WHERE t.id = 6;

UPDATE error t
SET t.message = 'The folder that you are trying to view does not exist.'
WHERE t.id = 13;

UPDATE error t
SET t.message = 'The folder that you are trying to delete is not empty. Please delete all its contents, then try again.'
WHERE t.id = 12;

UPDATE error t
SET t.message = 'You do not have permission to create a folder in this location.'
WHERE t.id = 20;

UPDATE error t
SET t.message = 'You do not have permission to post in this folder.'
WHERE t.id = 28;

#--- leaving error 166, as question bank still uses categories
