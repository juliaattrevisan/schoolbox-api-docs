# The values for 'summative_report_enabled' were used as a guide.
INSERT INTO config SET
name       ='curriculum_coverage_report_enabled',
value      ='0',
title      ='Enable Curriculum Coverage Report',
description='If ON, the Curriculum Coverage Report is available.', # TODO: Add location.
field_id   =3, # Boolean.
options    =NULL,
type_id    =15, # LMS Policy.
editable   ='false',
hidden     ='false',
required   ='false',
module     ='settings',
sequence   =NULL;
