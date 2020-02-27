-- Add a config option which defines resubmission window
INSERT INTO `config` (name, value, title, description, field_id, options, type_id, editable, hidden, required, module)
VALUES
(
    'lms_resubmit_window',
    '+1 week',
    'Resubmission Window',
    'The extension duration from the time a resubmission is requested, during which a student is allowed to resubmit. A custom participation plan is created if necessary, so that students can still submit their work even though an assessment may have closed.',
    (SELECT id FROM lu_config_field WHERE `field` = 'input'),
    null,
    (SELECT id FROM lu_config_type WHERE `type` = 'LMS Policy'),
    true,
    false,
    false,
    'settings'
);
