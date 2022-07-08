-- Create a hole for external_liss_exclusions
UPDATE config c
INNER JOIN lu_config_type lct on c.type_id = lct.id
SET c.sequence = c.sequence + 2
WHERE lct.type = 'External DB'
AND c.sequence > (SELECT c2.sequence FROM config c2 WHERE c2.name='external_liss_calendar_owner' LIMIT 1);

-- A config field containing event types
-- (a csv separated string)
-- These event types are skipped during imports

INSERT INTO `config`
    (`name`, `value`, `title`, `description`, `options`, `field_id`, `type_id`, `editable`, `hidden`, `required`, `module`, `sequence`)
VALUES
       ('external_liss_exclusions',
        'Duty, Study, RollClass',
        'External LISS Event Exclusions',
        'These event types will be excluded from import; separate multiple with commas.',
        '',
        (SELECT id FROM lu_config_field WHERE field = 'input'),
        (SELECT id FROM lu_config_type WHERE type = 'External DB'),
        'true',
        'false',
        'false',
        'settings',
        (SELECT c.sequence+1 FROM config c WHERE c.name='external_liss_calendar_owner' LIMIT 1)
    );

-- A config field containing event types
-- This event type is assigned to events which don't have one

INSERT INTO `config`
    (`name`, `value`, `title`, `description`, `options`, `field_id`, `type_id`, `editable`, `hidden`, `required`, `module`, `sequence`)
VALUES
       ('external_liss_default_event_type',
        '',
        'External LISS Default Event Type',
        'LISS events that did not have a type will be assigned the one you specify here. Leave it blank to skip importing events which do not have a type.',
        '',
        (SELECT id FROM lu_config_field WHERE field = 'input'),
        (SELECT id FROM lu_config_type WHERE type = 'External DB'),
        'true',
        'false',
        'false',
        'settings',
        (SELECT c.sequence+2 FROM config c WHERE c.name='external_liss_calendar_owner' LIMIT 1)
    );
