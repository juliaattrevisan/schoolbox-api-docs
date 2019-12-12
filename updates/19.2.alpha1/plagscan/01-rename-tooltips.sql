
UPDATE `config`
SET
    `description`='If enabled, students will have access to Similarity Reports',
    `title`='Allow Students to see Similarity Reports'
WHERE `name`='plagscan_allow_students_report_access';


UPDATE `config`
SET
    `description`='When students submit work this option will allow them to view the Similarity score they received. Note that the Similarity system must be enabled for this to work.',
    `title`='Display Similarity Score to Students'
WHERE `name`='plagscan_student_visible';


UPDATE `config`
SET
    `description`='Your PlagScan account\'s Client ID',
    `title`='PlagScan Client ID'
WHERE `name`='plagscan_client_id';

