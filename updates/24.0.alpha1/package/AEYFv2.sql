INSERT INTO package (title, description, location, installed, hidden)
VALUES ('Australian Early Years Learning Framework (v2)',
        'Attaches codes to the Australian Early Years Learning Framework (v2) Curriculum, to allow for the mapping of these to learning resources.',
        'AusEarlyYearsFrameworkCurriculumV2', 0, 'false')
;

UPDATE package SET hidden = 'true' WHERE location = 'EarlyYearsFrameworkCurriculum';
