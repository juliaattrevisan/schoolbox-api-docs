-- Dashboard components ignore the 'component_category' field.
-- In 17/20 cases, the value is NULL, in the other 3, the value is '1'
-- This causes a minor complication when inspecting the data, eg retrieving Homepage components in cat 1
-- This change standardises all dashboard components to value NULL
UPDATE
  `component_definition`
SET
  `component_category` = NULL
WHERE
  `module` = 'dashboard'
AND
  `component_category` IS NOT NULL;