INSERT INTO config SET
name       ='maintenance_mode',
value      =0,
title      ='Maintenance Mode',
description='Turn this on to prevent access to Schoolbox during maintenance periods.',
field_id   =3, # Boolean.
options    =NULL,
type_id    =2, # Security.
editable   ='true',
hidden     ='true', # Do not alllow superusers to switch on/off maintenance mode themselves in admin.
required   ='false',
module     ='settings',
sequence   =12;
