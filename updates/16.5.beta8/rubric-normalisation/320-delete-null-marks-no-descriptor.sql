-- TODO - Should be run after deleting invalid descriptors ?
-- Delete marks if:
-- * mark.descriptor does not exist
-- * mark.mark IS NULL
-- Without either of these data, this record is useless.
DELETE FROM rubric_mark WHERE id IN (
    SELECT * FROM (
      SELECT id
      FROM rubric_mark
      WHERE descriptor_id NOT IN (SELECT DISTINCT id from rubric_descriptor)
      AND mark IS NULL
    ) t
);
