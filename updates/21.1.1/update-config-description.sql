-- add pastoral flags in description
UPDATE `config` SET `description` =
'If toggled to ''ON'', pastoral flags and the following flags from Synergetic will show for each student on the attendance page:
MedicalAlertFlag,
SpecialNeedsFlag,
CommentWarningFlag,
ParentsSeparatedFlag,
CourtOrderFlag,
IndividualPlanFlag,
AnaphylaxisFlag.'
WHERE `name` = 'show_attendance_flags';
