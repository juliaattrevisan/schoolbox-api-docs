# Create and show the new package
INSERT INTO package (title, description, location, installed, hidden)
VALUES ('New South Wales Curriculum 23-27',
        'Attaches codes to the New South Wales Curriculum to allow for the mapping of these to learning resources.',
        'NSWCurriculum23', 0, 'false')
;

# Hide the old package
UPDATE package
SET hidden = 'true'
WHERE location = 'NSWCurriculum';
