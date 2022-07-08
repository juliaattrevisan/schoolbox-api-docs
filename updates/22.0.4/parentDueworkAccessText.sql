UPDATE config
    SET title = 'Hide Due Work from Parents',
    description = 'If toggled to ''ON'', parents will be prevented from being able to access the Due work calendar via the My Students Component.  They will also not receive notifications about due work.'
WHERE name = 'restrict_parent_duework_access'
