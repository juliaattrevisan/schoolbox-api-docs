# The values for 'summative_report_enabled' were used as a guide.
INSERT INTO config SET
name       ='curriculum_coverage_report_enabled',
value      ='0',
title      ='Enable Curriculum Coverage Report',
description='If ON, the Curriculum Coverage Report will be enabled.  This report audits curriculum coverage in courses and units.',
field_id   =(SELECT id FROM lu_config_field WHERE field = 'boolean'),
options    =NULL,
type_id    =(SELECT id FROM lu_config_type WHERE type = 'Modules'),
editable   ='false',
hidden     ='false',
required   ='false',
module     ='settings',
sequence   =NULL;
