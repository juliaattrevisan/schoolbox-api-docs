INSERT INTO config (
                    name, value, title, description, field_id, type_id, editable, hidden, required, module, sequence
                    )
VALUES (
        'skin_favicon',
        null,
        'App Icon',
        'Upload a square image of at least 192x192 pixels, that will be used as the favicon shown in browser tabs, bookmarks and when the app is installed on a device.',
        (select id from lu_config_field where field = 'file'),
        (select id from lu_config_type where type = 'Skin Config'),
        'true',
        'false',
        'false',
        'settings',
        (SELECT max(sequence) + 1
         FROM config c
         WHERE c.type_id = (select id from lu_config_type where type = 'Skin Config') LIMIT 1));


### update sequence for Admin > Personalisation > Skin Config
UPDATE config SET sequence = 1 WHERE name = 'skin_favicon';
UPDATE config SET sequence = 2 WHERE name = 'skin_logo_login';
UPDATE config SET sequence = 3 WHERE name = 'skin_login_bg';
UPDATE config SET sequence = 4 WHERE name = 'skin_colour_login_background';
UPDATE config SET sequence = 5 WHERE name = 'skin_colour_navigation_background';
UPDATE config SET sequence = 6 WHERE name = 'skin_colour_navigation_foreground';
UPDATE config SET sequence = 7 WHERE name = 'skin_colour_navigation_hover';
UPDATE config SET sequence = 8 WHERE name = 'skin_logo_square';
UPDATE config SET sequence = 9 WHERE name = 'skin_colour_offcanvas_background';
UPDATE config SET sequence = 10 WHERE name = 'skin_colour_offcanvas_foreground';
UPDATE config SET sequence = 11 WHERE name = 'skin_colour_offcanvas_hover';
UPDATE config SET sequence = 12 WHERE name = 'skin_logo_large';
UPDATE config SET sequence = 13 WHERE name = 'skin_colour_logo_background';
UPDATE config SET sequence = 14 WHERE name = 'accounts_menu_bg_colour';
UPDATE config SET sequence = 15 WHERE name = 'accounts_menu_text_colour';
UPDATE config SET sequence = 16 WHERE name = 'accounts_menu_hover_colour';
UPDATE config SET sequence = 17 WHERE name = 'skin_colour_body_bg';
UPDATE config SET sequence = 18 WHERE name = 'homepage_colour_head_font';
UPDATE config SET sequence = 19 WHERE name = 'homepage_colour_head_bg';
UPDATE config SET sequence = 20 WHERE name = 'homepage_colour_font';
UPDATE config SET sequence = 22 WHERE name = 'homepage_colour_bg_primary';
UPDATE config SET sequence = 23 WHERE name = 'skin_colour_content_ui_foreground';
UPDATE config SET sequence = 24 WHERE name = 'skin_colour_content_ui_background';
UPDATE config SET sequence = 25 WHERE name = 'skin_colour_content_ui_hover';
UPDATE config SET sequence = 26 WHERE name = 'skin_colour_content_ui_selected';
UPDATE config SET sequence = 27 WHERE name = 'skin_colour_accent_foreground';
UPDATE config SET sequence = 28 WHERE name = 'skin_colour_accent_background';
UPDATE config SET sequence = 29 WHERE name = 'skin_colour_accent_hover';
UPDATE config SET sequence = 30 WHERE name = 'skin_colour_form_input_border_color';
UPDATE config SET sequence = 31 WHERE name = 'skin_colour_form_background_hover_color';
UPDATE config SET sequence = 32 WHERE name = 'skin_colour_content_ui_submit_foreground';
UPDATE config SET sequence = 34 WHERE name = 'skin_colour_content_ui_submit_background';
UPDATE config SET sequence = 35 WHERE name = 'skin_custom_css';
UPDATE config SET sequence = 36 WHERE name = 'email_banner_image';
