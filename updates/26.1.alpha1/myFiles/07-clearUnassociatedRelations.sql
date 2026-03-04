UPDATE myFile
LEFT JOIN myFolder on myFile.parent = myFolder.id
SET myFile.parent = NULL
WHERE myFolder.id IS NULL;

UPDATE myFolder
LEFT JOIN myFolder AS parentFolder on myFolder.parent = parentFolder.id
SET myFolder.parent = NULL
WHERE parentFolder.id IS NULL;

UPDATE myFile
LEFT JOIN user ON myFile.owner = user.id
SET myFile.owner = NULL
WHERE user.id IS NULL;
