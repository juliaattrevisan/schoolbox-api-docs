# Update the Admin > Settings - LMS Policy > 'Default Visibility of Graded Due Work' setting to use the
# same string values as the mark_visibility enum columns.
#
# Migration of values
# -------------------
# Since the current options are:
#  * Staff only (integer value 0)
#  * Staff and Student (integer value 1)
#  * Staff, Students and their Parents (integer value 2)
#
# Mapping of current config settings to new config settings works as follows:
#  * submission_access = 0
#    => submission_access = staffOnly
#  * submission_access = 1
#    => submission_access = staffAndStudent
#  * submission_access = 2
#    => submission_access = staffStudentAndParent

-- Note the labels on the options must match those in the consts defined in IAssessable.php.
-- Only numerical values are expected here, but cases have been added for the new enum values
-- in case this is SQL is ever run again for whatever reason.
UPDATE config
SET `value` = CASE `value`
        WHEN '0' THEN 'staffOnly'
        WHEN '1' THEN 'staffAndStudent'
        WHEN '2' THEN 'staffStudentAndParent'
        WHEN 'staffOnly' THEN 'staffOnly'
        WHEN 'staffAndStudent' THEN 'staffAndStudent'
        WHEN 'staffAndParent' THEN 'staffAndParent'
        WHEN 'staffStudentAndParent' THEN 'staffStudentAndParent'
        ELSE 'staffOnly'
    END,
    `options` = 'staffOnly;Staff Only:staffAndStudent;Staff & Student:staffAndParent;Staff & Parent:staffStudentAndParent;Staff, Student & Parent'
WHERE `name` = 'submission_access';
