UPDATE config
SET description = 'Insert the custom view name for the User Provisioning System to use for Students. For example: uvSchoolboxStudents'
WHERE `name` = 'external_custom_ups_student';
UPDATE config
SET description = 'Insert the custom view name for the User Provisioning System to use for Parents. For example: uvSchoolboxParents'
WHERE `name` = 'external_custom_ups_parent';
