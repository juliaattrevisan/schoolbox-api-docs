-- TSC
-- their connector surfaces LECaseManagerName, LECaseManagerUserId
-- optionally displaying it in the profile screens if they're meaningful

INSERT INTO `profile_field`
(roletype, field, display_as, sequence, enabled, term, icon, css, click_to_reveal, is_external)
VALUES
('student','LECaseManagerName','Learning Enrichment Case Manager',(
SELECT MAX(sequence)+1
FROM profile_field pf
WHERE roletype = 'student'
),1,NULL,NULL,NULL,0,1);

-- Despite this field existing, it'll never render to users: a customised student.twig
-- intercepts the default rendering

INSERT INTO `profile_field`
(roletype, field, display_as, sequence, enabled, term, icon, css, click_to_reveal, is_external)
VALUES
('student','LECaseManagerID','Learning Enrichment Case Manager ID',(
    SELECT MAX(sequence)+1
    FROM profile_field pf
    WHERE roletype = 'student'
),1,NULL,NULL,NULL,0,1);

-- they also had a custom button in the Student roletype which would have been visible to Parent or Staff

