INSERT INTO config SET
name       ='dataset_instance',
value      =0,
title      ='Dataset Instance',
description='Turn this on if this instance is a template for generating datasets',
field_id   =(SELECT id FROM lu_config_field WHERE field='boolean'),
options    =NULL,
type_id    =(SELECT id FROM lu_config_type WHERE `type`='Security'),
editable   ='false',
hidden     ='true',
required   ='false',
module     ='settings',
sequence   =null;
