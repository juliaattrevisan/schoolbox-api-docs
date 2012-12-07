UPDATE `config` SET `value` = 'Staff Student Parent ParentRelation Class Enrolment Timetable' WHERE `name` LIKE 'sync_patterns';

-- class: Classes.csv
INSERT INTO config (`name`, `value`, `title`, `description`, `type`, `required`) VALUES (
  'external_sync_class_csv', '/vagrant/tools/synchronise/stpatricks/testdata/Classes.csv', 'Synchronise Class: CSV file', 'Location of CSV file for class synchronisation', 'External DB', 'true'
);

-- enrolment: Class\ Enrolment.csv
INSERT INTO config (`name`, `value`, `title`, `description`, `type`, `required`) VALUES (
  'external_sync_enrolment_csv', '/vagrant/tools/synchronise/stpatricks/testdata/Class Enrolment.csv', 'Synchronise Enrolment: CSV file', 'Location of CSV file for enrolment synchronisation', 'External DB', 'true'
);

-- parent: Parent.csv
INSERT INTO config (`name`, `value`, `title`, `description`, `type`, `required`) VALUES (
  'external_sync_parent_csv', '/vagrant/tools/synchronise/stpatricks/testdata/Parent.csv', 'Synchronise Parent: CSV file', 'Location of CSV file for parent synchronisation', 'External DB', 'true'
);

-- parentrelation: Relationships.csv
INSERT INTO config (`name`, `value`, `title`, `description`, `type`, `required`) VALUES (
  'external_sync_parentrelation_csv', '/vagrant/tools/synchronise/stpatricks/testdata/Relationships.csv', 'Synchronise Parent Relation: CSV file', 'Location of CSV file for parent relation synchronisation', 'External DB', 'true'
);

-- staff: Teacher.csv
INSERT INTO config (`name`, `value`, `title`, `description`, `type`, `required`) VALUES (
  'external_sync_staff_csv', '/vagrant/tools/synchronise/stpatricks/testdata/Teacher.csv', 'Synchronise Staff: CSV file', 'Location of CSV file for staff synchronisation', 'External DB', 'true'
);

-- student: Students.csv
INSERT INTO config (`name`, `value`, `title`, `description`, `type`, `required`) VALUES (
  'external_sync_student_csv', '/vagrant/tools/synchronise/stpatricks/testdata/Students.csv', 'Synchronise Student: CSV file', 'Location of CSV file for student synchronisation', 'External DB', 'true'
);

-- timetable: Timetable.csv
INSERT INTO config (`name`, `value`, `title`, `description`, `type`, `required`) VALUES (
  'external_sync_timetable_csv', '/vagrant/tools/synchronise/stpatricks/testdata/Timetable.csv', 'Synchronise Timetable: CSV file', 'Location of CSV file for timetable synchronisation', 'External DB', 'true'
);

