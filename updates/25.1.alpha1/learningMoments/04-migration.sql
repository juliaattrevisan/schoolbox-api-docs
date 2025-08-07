-- Existing learning moments are migrated into Published state.
--     - published_at equals updated_at
--     - published_by equals created_by

UPDATE evidence
SET published_by = created_by,
    published_at = updated_at;
