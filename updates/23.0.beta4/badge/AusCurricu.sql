INSERT INTO package (title, description, location, installed, hidden)
VALUES ('Australian Curriculum (v9) - Badges',
        'A badge set with codes attached to the Australian Curriculum (v9) to allow you to celebrate the achievements of students. The Australian Curriculum (v9) - General Capabilities package must be installed for these badges to be installed.',
        'AustralianCurriculumV9Badges', 0, 'false')
;

UPDATE package set description = 'Attaches codes to the Australian Curriculum (v9) - General Capabilities, to allow for the mapping of these to learning resources. This is required to install the Australian Curriculum (v9) - Badges set.'
WHERE location = 'AustralianCurriculumV9GeneralCapabilities';
