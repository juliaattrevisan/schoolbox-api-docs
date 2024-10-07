INSERT INTO package (title, description, location, installed, hidden)
VALUES ('Victorian Curriculum Version 2.0',
        'Attaches codes to the Victorian Curriculum (v2.0), to allow for mapping of the core Learning Areas, Capabilities and Cross-curriculum priorities, to learning resources.',
        'VictorianCurriculumV2', 0, 'false')
;

### update existing historic Victorian Curriculum packages to be hidden
UPDATE package
SET title      = 'Victorian Curriculum Version 1',
    hidden      = 'true'
WHERE location  = 'VictorianCurriculum';
