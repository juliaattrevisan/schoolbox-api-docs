SET @configTypeId = (SELECT id FROM lu_config_type WHERE type = 'Debugging');
SET @configSequence = (SELECT MAX(sequence) + 1 FROM config WHERE type_id = @configTypeId);
INSERT INTO config (
    name,
    value,
    title,
    description,
    field_id,
    options,
    type_id,
    editable,
    hidden,
    required,
    module,
    sequence
) VALUES (
    'sentry_error_sample_rate',
    -- by default: sample every error
    '1',
    'Sentry error sampling rate',
    'Sampling rate for errors: this proportion of errors will be sent to Sentry.  (0: no errors, 1: all errors)',
    (SELECT id FROM lu_config_field WHERE `field` = 'number'),
    '',
    @configTypeId,
    'true',
    'false',
    'false',
    'settings',
    @configSequence + 1
);
