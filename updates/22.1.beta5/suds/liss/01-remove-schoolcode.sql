-- nobody uses this, apparently
-- it was originally used to identify an event_source
-- but it's not fundamentally necessary
DELETE FROM `config`
WHERE name = 'external_liss_schoolcode'
LIMIT 1;