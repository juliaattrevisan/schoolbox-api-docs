-- The xml_cache_timeout max config field now has restrictions, to prevent it from being set to 0
UPDATE `config`
SET
    `options` = '{"min": 30}'
WHERE
    `name` = "xml_cache_timeout";

-- Restrict the value of existing configs to the new range
UPDATE `config`
SET
    `value` = "30"
WHERE
    `name` = "xml_cache_timeout"
  AND (
    `value` = ""
        OR `value` < "30"
    );

-- The external_cache_timeout max config field now has restrictions, to prevent it from being set to 0
UPDATE `config`
SET
    `options` = '{"min": 30}'
WHERE
    `name` = "external_cache_timeout";

-- Restrict the value of existing configs to the new range
UPDATE `config`
SET
    `value` = "30"
WHERE
    `name` = "external_cache_timeout"
  AND (
    `value` = ""
        OR `value` < "30"
    );

-- The schooldata_import_timeout max config field now has restrictions, to prevent it from being set to 0
UPDATE `config`
SET
    `options` = '{"min": 30}'
WHERE
    `name` = "schooldata_import_timeout";

-- Restrict the value of existing configs to the new range
UPDATE `config`
SET
    `value` = "30"
WHERE
    `name` = "schooldata_import_timeout"
  AND (
    `value` = ""
        OR `value` < "30"
    );