-- Updating the default role permissions for unconfirmed notifications in the IB registration namespace to instant, mobile and email delivery type
UPDATE msg_route_perm
SET role_default = 'IN,MO,EM'
WHERE msg_source_id = (
    SELECT id
    FROM msg_source
    WHERE namespace = (SELECT id FROM msg_namespace WHERE slug = 'ib_registration')
      AND `key` = 'unconfirmed'
      AND role_type = 'student'
    )
  AND role_id IN (
    SELECT `groups`.id
    FROM `groups`
    WHERE `groups`.`group_type` = 'student'
);
