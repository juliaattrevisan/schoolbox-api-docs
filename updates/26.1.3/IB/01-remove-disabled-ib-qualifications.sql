-- Remove any qualifications that are assigned to user already if they are IB related and they don't have IB enabled
DELETE uq
FROM user_qualification uq
INNER JOIN lu_qualification q ON q.id = uq.qualification_id
WHERE q.system_purpose_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM config c
      WHERE c.name = 'ib_enabled'
        AND c.value = '1';
  );

-- Remove any IB qualifications if IB is not enabled
DELETE q
FROM lu_qualification q
WHERE q.system_purpose_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM config c
      WHERE c.name = 'ib_enabled'
        AND LOWER(c.value) IN ('1', 'true', 'yes', 'on')
  );
