-- Don't let user input empty string - ambiguous whether this should be interpreted as NULL or 0, which have very different behaviour.
UPDATE config
SET required = 'true'
WHERE name = 'external_cache_timeout';
