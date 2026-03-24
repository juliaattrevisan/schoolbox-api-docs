UPDATE config
SET
    options = JSON_MERGE_PATCH(
        CASE
            WHEN options IS NULL
            OR TRIM(options) = ''
            OR JSON_VALID(options) = 0 THEN JSON_OBJECT()
            ELSE options
        END,
        JSON_OBJECT(
            'customProperty',
            '--navigation-foreground'
        )
    )
WHERE
    name = 'skin_colour_navigation_foreground';

UPDATE config
SET
    options = JSON_MERGE_PATCH(
        CASE
            WHEN options IS NULL
            OR TRIM(options) = ''
            OR JSON_VALID(options) = 0 THEN JSON_OBJECT()
            ELSE options
        END,
        JSON_OBJECT(
            'customProperty',
            '--navigation-background'
        )
    )
WHERE
    name = 'skin_colour_navigation_background';

UPDATE config
SET
    options = JSON_MERGE_PATCH(
        CASE
            WHEN options IS NULL
            OR TRIM(options) = ''
            OR JSON_VALID(options) = 0 THEN JSON_OBJECT()
            ELSE options
        END,
        JSON_OBJECT(
            'customProperty',
            '--navigation-hover'
        )
    )
WHERE
    name = 'skin_colour_navigation_hover';

UPDATE config
SET
    options = JSON_MERGE_PATCH(
        CASE
            WHEN options IS NULL
            OR TRIM(options) = ''
            OR JSON_VALID(options) = 0 THEN JSON_OBJECT()
            ELSE options
        END,
        JSON_OBJECT(
            'customProperty',
            '--off-canvas-foreground'
        )
    )
WHERE
    name = 'skin_colour_offcanvas_foreground';

UPDATE config
SET
    options = JSON_MERGE_PATCH(
        CASE
            WHEN options IS NULL
            OR TRIM(options) = ''
            OR JSON_VALID(options) = 0 THEN JSON_OBJECT()
            ELSE options
        END,
        JSON_OBJECT(
            'customProperty',
            '--off-canvas-background'
        )
    )
WHERE
    name = 'skin_colour_offcanvas_background';

UPDATE config
SET
    options = JSON_MERGE_PATCH(
        CASE
            WHEN options IS NULL
            OR TRIM(options) = ''
            OR JSON_VALID(options) = 0 THEN JSON_OBJECT()
            ELSE options
        END,
        JSON_OBJECT(
            'customProperty',
            '--off-canvas-hover'
        )
    )
WHERE
    name = 'skin_colour_offcanvas_hover';

UPDATE config
SET
    options = JSON_MERGE_PATCH(
        CASE
            WHEN options IS NULL
            OR TRIM(options) = ''
            OR JSON_VALID(options) = 0 THEN JSON_OBJECT()
            ELSE options
        END,
        JSON_OBJECT(
            'customProperty',
            '--accounts-menu-foreground'
        )
    )
WHERE
    name = 'accounts_menu_text_colour';

UPDATE config
SET
    options = JSON_MERGE_PATCH(
        CASE
            WHEN options IS NULL
            OR TRIM(options) = ''
            OR JSON_VALID(options) = 0 THEN JSON_OBJECT()
            ELSE options
        END,
        JSON_OBJECT(
            'customProperty',
            '--accounts-menu-background'
        )
    )
WHERE
    name = 'accounts_menu_bg_colour';

UPDATE config
SET
    options = JSON_MERGE_PATCH(
        CASE
            WHEN options IS NULL
            OR TRIM(options) = ''
            OR JSON_VALID(options) = 0 THEN JSON_OBJECT()
            ELSE options
        END,
        JSON_OBJECT(
            'customProperty',
            '--accounts-menu-hover'
        )
    )
WHERE
    name = 'accounts_menu_hover_colour';

UPDATE config
SET
    options = JSON_MERGE_PATCH(
        CASE
            WHEN options IS NULL
            OR TRIM(options) = ''
            OR JSON_VALID(options) = 0 THEN JSON_OBJECT()
            ELSE options
        END,
        JSON_OBJECT(
            'customProperty',
            '--content-ui-foreground'
        )
    )
WHERE
    name = 'skin_colour_content_ui_foreground';

UPDATE config
SET
    options = JSON_MERGE_PATCH(
        CASE
            WHEN options IS NULL
            OR TRIM(options) = ''
            OR JSON_VALID(options) = 0 THEN JSON_OBJECT()
            ELSE options
        END,
        JSON_OBJECT(
            'customProperty',
            '--content-ui-background'
        )
    )
WHERE
    name = 'skin_colour_content_ui_background';

UPDATE config
SET
    options = JSON_MERGE_PATCH(
        CASE
            WHEN options IS NULL
            OR TRIM(options) = ''
            OR JSON_VALID(options) = 0 THEN JSON_OBJECT()
            ELSE options
        END,
        JSON_OBJECT(
            'customProperty',
            '--content-ui-hover'
        )
    )
WHERE
    name = 'skin_colour_content_ui_hover';

UPDATE config
SET
    options = JSON_MERGE_PATCH(
        CASE
            WHEN options IS NULL
            OR TRIM(options) = ''
            OR JSON_VALID(options) = 0 THEN JSON_OBJECT()
            ELSE options
        END,
        JSON_OBJECT(
            'customProperty',
            '--content-ui-selected'
        )
    )
WHERE
    name = 'skin_colour_content_ui_selected';

UPDATE config
SET
    options = JSON_MERGE_PATCH(
        CASE
            WHEN options IS NULL
            OR TRIM(options) = ''
            OR JSON_VALID(options) = 0 THEN JSON_OBJECT()
            ELSE options
        END,
        JSON_OBJECT(
            'customProperty',
            '--content-ui-submit-foreground'
        )
    )
WHERE
    name = 'skin_colour_content_ui_submit_foreground';

UPDATE config
SET
    options = JSON_MERGE_PATCH(
        CASE
            WHEN options IS NULL
            OR TRIM(options) = ''
            OR JSON_VALID(options) = 0 THEN JSON_OBJECT()
            ELSE options
        END,
        JSON_OBJECT(
            'customProperty',
            '--content-ui-submit-background'
        )
    )
WHERE
    name = 'skin_colour_content_ui_submit_background';

UPDATE config
SET
    options = JSON_MERGE_PATCH(
        CASE
            WHEN options IS NULL
            OR TRIM(options) = ''
            OR JSON_VALID(options) = 0 THEN JSON_OBJECT()
            ELSE options
        END,
        JSON_OBJECT(
            'customProperty',
            '--accent-foreground'
        )
    )
WHERE
    name = 'skin_colour_accent_foreground';

UPDATE config
SET
    options = JSON_MERGE_PATCH(
        CASE
            WHEN options IS NULL
            OR TRIM(options) = ''
            OR JSON_VALID(options) = 0 THEN JSON_OBJECT()
            ELSE options
        END,
        JSON_OBJECT(
            'customProperty',
            '--accent-background'
        )
    )
WHERE
    name = 'skin_colour_accent_background';

UPDATE config
SET
    options = JSON_MERGE_PATCH(
        CASE
            WHEN options IS NULL
            OR TRIM(options) = ''
            OR JSON_VALID(options) = 0 THEN JSON_OBJECT()
            ELSE options
        END,
        JSON_OBJECT(
            'customProperty',
            '--accent-hover'
        )
    )
WHERE
    name = 'skin_colour_accent_hover';

UPDATE config
SET
    options = JSON_MERGE_PATCH(
        CASE
            WHEN options IS NULL
            OR TRIM(options) = ''
            OR JSON_VALID(options) = 0 THEN JSON_OBJECT()
            ELSE options
        END,
        JSON_OBJECT(
            'customProperty',
            '--logo-background'
        )
    )
WHERE
    name = 'skin_colour_logo_background';

UPDATE config
SET
    options = JSON_MERGE_PATCH(
        CASE
            WHEN options IS NULL
            OR TRIM(options) = ''
            OR JSON_VALID(options) = 0 THEN JSON_OBJECT()
            ELSE options
        END,
        JSON_OBJECT(
            'customProperty',
            '--body-background'
        )
    )
WHERE
    name = 'skin_colour_body_bg';

UPDATE config
SET
    options = JSON_MERGE_PATCH(
        CASE
            WHEN options IS NULL
            OR TRIM(options) = ''
            OR JSON_VALID(options) = 0 THEN JSON_OBJECT()
            ELSE options
        END,
        JSON_OBJECT(
            'customProperty',
            '--login-form-background'
        )
    )
WHERE
    name = 'skin_colour_login_background';

UPDATE config
SET
    options = JSON_MERGE_PATCH(
        CASE
            WHEN options IS NULL
            OR TRIM(options) = ''
            OR JSON_VALID(options) = 0 THEN JSON_OBJECT()
            ELSE options
        END,
        JSON_OBJECT(
            'customProperty',
            '--form-border-color'
        )
    )
WHERE
    name = 'skin_colour_form_input_border_color';

UPDATE config
SET
    options = JSON_MERGE_PATCH(
        CASE
            WHEN options IS NULL
            OR TRIM(options) = ''
            OR JSON_VALID(options) = 0 THEN JSON_OBJECT()
            ELSE options
        END,
        JSON_OBJECT(
            'customProperty',
            '--form-bg-hover-color'
        )
    )
WHERE
    name = 'skin_colour_form_background_hover_color';
