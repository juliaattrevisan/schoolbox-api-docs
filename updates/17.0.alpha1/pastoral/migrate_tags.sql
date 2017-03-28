-- Rename corrupted integer tags as what they should be
UPDATE pastoral_tags AS p1
INNER JOIN pastoral_tags AS p2 ON p1.id = p2.name
SET p2.`name` = p1.`name`
