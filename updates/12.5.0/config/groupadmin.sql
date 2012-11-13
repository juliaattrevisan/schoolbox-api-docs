/* Ensure group_staff_only has the correct title and description of it's functionality */
UPDATE config SET title='Allow only staff to be Group Admins',description='Disabling this option allows students and parents to be promoted to group admin. Beware of the consequences.' WHERE name='group_staff_only';
