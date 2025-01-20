# The values for 'summative_report_enabled' were used as a guide.
INSERT INTO config SET
name       = 'amazon_ses_username',
value      = '',
title      = 'SES Username',
description= 'Please Enter the username here from Amazon SES credentials',
field_id   = (SELECT id FROM lu_config_field WHERE field = 'input'),
options    = NULL,
type_id    = (SELECT id FROM lu_config_type WHERE type = 'Email'),
editable   = 'true',
hidden     = 'false',
required   = 'false',
module     = 'settings',
sequence   = (SELECT MAX(sequence) + 1 FROM config as config2 WHERE type_id = (SELECT id FROM lu_config_type WHERE type = 'Email'));

INSERT INTO config SET
name       = 'amazon_ses_password',
value      = '',
title      = 'SES Password',
description= 'Please Enter the password here from Amazon SES credentials',
field_id   = (SELECT id FROM lu_config_field WHERE field = 'password'),
options    = NULL,
type_id    = (SELECT id FROM lu_config_type WHERE type = 'Email'),
editable   = 'true',
hidden     = 'false',
required   = 'false',
module     = 'settings',
sequence   = (SELECT MAX(sequence) + 1 FROM config as config2 WHERE type_id = (SELECT id FROM lu_config_type WHERE type = 'Email'));

INSERT INTO config SET
name       = 'amazon_ses_region',
value      = '',
title      = 'SES Region',
description= 'Please Enter the region here from Amazon SES credentials',
field_id   = (SELECT id FROM lu_config_field WHERE field = 'input'),
options    = NULL,
type_id    = (SELECT id FROM lu_config_type WHERE type = 'Email'),
editable   = 'true',
hidden     = 'false',
required   = 'false',
module     = 'settings',
sequence   = (SELECT MAX(sequence) + 1 FROM config as config2 WHERE type_id = (SELECT id FROM lu_config_type WHERE type = 'Email'));
