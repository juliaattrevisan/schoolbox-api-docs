-- Prior to setting condition/effect arguments as JSON columns:
-- delete any rows which are not valid JSON
DELETE FROM ups_condition WHERE JSON_LENGTH(arguments) IS NULL;
DELETE FROM ups_effect WHERE JSON_LENGTH(arguments) IS NULL;

-- And convert
ALTER TABLE ups_condition MODIFY arguments JSON NULL;
ALTER TABLE ups_effect MODIFY arguments JSON NULL;
