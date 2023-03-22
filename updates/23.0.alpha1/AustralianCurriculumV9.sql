INSERT INTO package (title, description, location, installed, hidden)
VALUES ('Australian Currciulum v9',
        'Attaches codes to the Australian Currciulum v9 to allow for the mapping of these to learning resources.',
        'AustralianCurriculumV9', 0, 'false');

### update existing historic Australian Curriculum packages to have the version in the names V8
UPDATE package
SET description = 'Over 300 badges linked to the Australian Curriculum v8 General Capabilities, including Critical and Creating Thinking, ICT, Literacy and Numeracy. The General Capabilities package must be installed for these badges to be installed.',
    hidden      = 'true'
WHERE location  = 'AusGeneralCapabilitiesBadge';

UPDATE package
SET description = 'A badge set with codes attached to the Australian Curriculum v8 Personal and Social Capability, and Intercultural and Ethical Understandings, to allow you track the personal development of students. The General Capabilities package must be installed for these badges to be installed.',
    hidden      = 'true'
WHERE location  = 'AusPersonalDevelopmentBadge';

UPDATE package
SET title       = 'Australian Curriculum v8 - Cross-curriculum Priorities',
    description = 'Attaches codes to the Australian Curriculum v8 Cross-curriculum priorities, to allow for the mapping of these to learning resources.',
    hidden      = 'true'
WHERE location  = 'AusCrossCurricular';

UPDATE package
SET title       = 'Australian Curriculum v8 - General Capabilities',
    description = 'Attaches codes to the Australian Curriculum v8 General Capabilities, to allow for the mapping of these to learning resources.  This is required to install the General Capability Badge set.',
    hidden      = 'true'
WHERE location  = 'AusGeneralCapabilities';

