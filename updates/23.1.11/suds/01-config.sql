
-- Additive Mode for liss
-- The external_liss_storage_logging is redundant now; we always capture payloads
-- but we can repurpose this field in situ since it's more or less what we needed

UPDATE `config`
SET
    `title` = 'External LISS Additive Mode',
    `name` = 'external_liss_additive_mode',
    `description` = 'Toggle this on if your LISS data supplements (adds to) your SIS timetable data; otherwise leave it off if LISS is the exclusive source of truth for timetable data.',
    `value` = 0
WHERE `name` = 'external_liss_storage_logging'
LIMIT 1;

