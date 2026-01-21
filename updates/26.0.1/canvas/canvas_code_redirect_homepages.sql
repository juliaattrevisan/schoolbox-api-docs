update config set
    `field_id` = (SELECT id FROM lu_config_field WHERE field = 'selectKey')
    where name = 'canvas_code_redirect_homepage' LIMIT 1;
