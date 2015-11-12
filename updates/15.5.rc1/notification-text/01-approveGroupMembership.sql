-- Change wording of group approval message
UPDATE msg_source ms
INNER JOIN msg_namespace mn ON ms.`namespace` = mn.`id`
SET ms.`description` = 'you are added to or removed from a group'
WHERE mn.`slug` = 'group'
  AND ms.key = 'approved';
