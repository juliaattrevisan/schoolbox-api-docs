-- Prior to setting condition/effect arguments as JSON columns:
-- delete any rows which are not valid JSON
DELETE FROM ups_condition WHERE JSON_VALID(arguments) IS FALSE;
DELETE FROM ups_effect WHERE JSON_VALID(arguments) IS FALSE;

-- And convert
ALTER TABLE ups_condition MODIFY arguments JSON NULL;
ALTER TABLE ups_effect MODIFY arguments JSON NULL;
