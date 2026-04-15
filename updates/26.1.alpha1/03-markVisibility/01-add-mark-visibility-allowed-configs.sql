# Modify the Admin > Settings - LMS Policy page as follows:
#  * Move all settings after sequence 19 down by two positions (so the next setting moves from sequence 20 to sequence 22)
#  * Insert two new settings at sequences 20 and 21
#  * Deprecate and hide the setting at sequence 19 ('Restrict Visibility of Graded Due Work').

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

# Move settings after sequence 19 forward by two positions.
UPDATE config SET sequence = sequence + 2 WHERE module = 'settings' AND type_id = @typeIdLmsPolicy AND sequence > 19;

# Create new setting at sequence 20.
INSERT INTO config (`name`, `title`, `value`, `description`, `field_id`,  `type_id`, `editable`, `hidden`, `required`, `module`, `sequence`)
VALUES (
    'mark_visibility_allow_students',
    'Enable grade visibility options involving students',
    (
        CASE @submissionVisibilityMax
            WHEN 0 THEN ‘0’
            WHEN 1 THEN ‘1’
            WHEN 2 THEN ‘1’
        END
    ),
    "Toggling this to ‘ON’ will allow grade visibility options involving students to be selected.  Changing this setting will not affect already published grades.",
    @fieldIdBoolean,
    @typeIdLmsPolicy,
    'true',
    'false',
    'false',
    'settings',
    20
);

# Create new setting at sequence 21.
INSERT INTO config (`name`, `title`, `value`, `description`, `field_id`,  `type_id`, `editable`, `hidden`, `required`, `module`, `sequence`)
VALUES (
    'mark_visibility_allow_parents',
    'Enable grade visibility options involving parents',
    (
        CASE @submissionVisibilityMax
            WHEN 0 THEN ‘0’
            WHEN 1 THEN ‘0’
            WHEN 2 THEN ‘1’
        END
    ),
    "Toggling this to ‘ON’ will allow grade visibility options involving parents to be selected.  Changing this setting will not affect already published grades.",
    @fieldIdBoolean,
    @typeIdLmsPolicy,
    'true',
    'false',
    'false',
    'settings',
    21
);

# Deprecate old setting.
UPDATE config SET name = '_submission_visibility_max', hidden = 'true', title = CONCAT(title, ' (deprecated)') WHERE name = 'submission_visibility_max';
