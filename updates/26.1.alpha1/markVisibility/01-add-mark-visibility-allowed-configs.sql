# Modify the Admin > Settings - LMS Policy page as follows:
#  * Move all settings after 'Restrict Visibility of Graded Due Work' down by two positions.
#  * Insert two new settings immediately after 'Restrict Visibility of Graded Due Work'.
#  * Delete the old 'Restrict Visibility of Graded Due Work' setting.

# Migration of values
# -------------------
# Since the current options are:
#  * Staff only (integer value 0)
#  * Staff and Student (integer value 1)
#  * Staff, Students and their Parents (integer value 2)
#
# Mapping of current config settings to new config settings would work as follows:
#  * submission_visibility_max = 0
#    => mark_visibility_allow_students = 0, mark_visibility_allow_parents = 0
#  * submission_visibility_max = 1
#    => mark_visibility_allow_students = 1, mark_visibility_allow_parents = 0
#  * submission_visibility_max = 2
#    => mark_visibility_allow_students = 1, mark_visibility_allow_parents = 1

# Save constants for use later.
SET @typeIdLmsPolicy = (SELECT id FROM lu_config_type WHERE type = 'LMS Policy');
SET @fieldIdBoolean = (SELECT id FROM lu_config_field WHERE field = 'boolean');
SET @submissionVisibilityMax = (SELECT value FROM config WHERE name='submission_visibility_max');
SET @submissionVisibilityMaxSequence = (SELECT sequence FROM config WHERE name='submission_visibility_max');

# Move settings after the old setting forward by two positions.
UPDATE config
SET sequence = sequence + 2
WHERE module = 'settings'
    AND type_id = @typeIdLmsPolicy
    AND sequence > @submissionVisibilityMaxSequence;

# Create new setting immediately after the old setting.
INSERT INTO config (`name`, `title`, `value`, `description`, `field_id`,  `type_id`, `editable`, `hidden`, `required`, `module`, `sequence`)
VALUES (
    'mark_visibility_allow_students',
    'Enable feedback visibility options involving students',
    (
        CASE @submissionVisibilityMax
            WHEN 0 THEN '0'
            WHEN 1 THEN '1'
            WHEN 2 THEN '1'
            ELSE '0'
        END
    ),
    "Toggling this to 'ON' will allow feedback visibility options involving students to be selected.  Changing this setting will not affect already published feedback.",
    @fieldIdBoolean,
    @typeIdLmsPolicy,
    'true',
    'false',
    'false',
    'settings',
    @submissionVisibilityMaxSequence + 1
);

# Create new setting immediately after the preceding new setting.
INSERT INTO config (`name`, `title`, `value`, `description`, `field_id`,  `type_id`, `editable`, `hidden`, `required`, `module`, `sequence`)
VALUES (
    'mark_visibility_allow_parents',
    'Enable feedback visibility options involving parents',
    (
        CASE @submissionVisibilityMax
            WHEN 0 THEN '0'
            WHEN 1 THEN '0'
            WHEN 2 THEN '1'
            ELSE '0'
        END
    ),
    "Toggling this to 'ON' will allow feedback visibility options involving parents to be selected.  Changing this setting will not affect already published grades.",
    @fieldIdBoolean,
    @typeIdLmsPolicy,
    'true',
    'false',
    'false',
    'settings',
    @submissionVisibilityMaxSequence + 2
);

# Delete old setting.
DELETE FROM config WHERE name = 'submission_visibility_max';
