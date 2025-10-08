/**
 * Delete Sentry config options:
 * these are no longer controllable via database config.
 */
DELETE FROM config WHERE name IN (
    'sentry_error_sample_rate',
    'sentry_trace_default_sample_rate',
    'sentry_trace_custom_sample_rates'
);
