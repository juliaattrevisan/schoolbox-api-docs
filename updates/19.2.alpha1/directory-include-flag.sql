-- Set restrict_parent_emails to 1 if the external_type is one of the affected connectors
UPDATE config c1
LEFT JOIN config AS c2 ON c2.name = 'external_type' AND c2.value IN ('denbigh', 'edumate', 'maze', 'schooledge')
SET c1.value = IF(c2.configID IS NOT NULL, 1, c1.value)
WHERE c1.name = 'restrict_parent_emails';
