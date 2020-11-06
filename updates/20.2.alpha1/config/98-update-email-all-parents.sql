UPDATE config SET
`title` = 'Allow Email All Parents',
`description` = "If toggled to 'ON', the 'email all' button will be enabled, allowing for staff to bulk email parents from a class list or a student's profile."
WHERE `name` = 'email_all_parents';
