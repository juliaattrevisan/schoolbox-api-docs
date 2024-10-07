INSERT INTO package (title, description, location, installed, hidden)
VALUES ('Victorian Curriculum Version 2.0',
        'The Victorian Curriculum v 2.0 package makes the core Learning Area, Capabilities and Cross-curriculum priorities available in the Schoolbox Curriculum mapping module',
        'VictorianCurriculumV2', 0, 'false')
;

### update existing historic Victorian Curriculum packages to be hidden
UPDATE package
SET title      = 'Victorian Curriculum Version 1',
    hidden      = 'true'
WHERE location  = 'VictorianCurriculum';
