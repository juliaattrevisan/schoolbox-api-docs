/* We cannot support leave notifications yet */
DELETE `msg_source`, `msg_namespace`
FROM `msg_namespace`
LEFT JOIN `msg_source` ON msg_namespace.id = msg_source.`namespace`
WHERE msg_namespace.slug = 'leave'