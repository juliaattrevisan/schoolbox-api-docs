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
    'sentry_trace_default_sample_rate',
    -- by default: trace 5 in every 10k requests
    '0.0005',
    'Default Sentry performance tracing sampling rate',
    'Default sampling rate for performance tracing: this proportion of requests will have their performance traces sent to Sentry.  (0: no requests, 1: all requests)',
    (SELECT id FROM lu_config_field WHERE `field` = 'number'),
    '',
    @configTypeId,
    'true',
    'false',
    'false',
    'settings',
    @configSequence + 1
 ), (
    'sentry_trace_custom_sample_rates',
    '',
    'Custom Sentry performance tracing sampling rates',
    'A CSV of custom sampling rates for performance tracing: any transaction whose name wildcard-matches the first column is instead sampled at the rate in the second column.',
    (SELECT id FROM lu_config_field WHERE `field` = 'textarea'),
    '',
    @configTypeId,
    'true',
    'false',
    'false',
    'settings',
    @configSequence + 2
 );
