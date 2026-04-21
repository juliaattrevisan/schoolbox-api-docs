-- Seed new skin configuration settings and map them into existing skin categories/subcategories.

-- Add new config type for Sonar Skin Configs
-- Hard-code the ID to match ConfigMapper::TYPE_SONAR_SKIN_CONFIG.
INSERT INTO
    lu_config_type (id, type, sequence)
VALUES (
    28,
    'Sonar Skin Configs',
    (
        SELECT COALESCE(MAX(sequence), 0) + 1
        FROM lu_config_type
    )
);

-- New settings will be under Sonar Skin Configs config type
SET
    @sonar_config_type_id = (
        SELECT id
        FROM lu_config_type
        WHERE
            type = 'Sonar Skin Configs'
        LIMIT 1
    );

-- Preset field types for new skin config settings
SET
    @config_colour_field_id = (
        SELECT id
        FROM lu_config_field
        WHERE
            field = 'colour'
        LIMIT 1
    );

SET
    @config_select_field_id = (
        SELECT id
        FROM lu_config_field
        WHERE
            field = 'select'
        LIMIT 1
    );

SET
    @config_checkbox_field_id = (
        SELECT id
        FROM lu_config_field
        WHERE
            field = 'checkbox'
        LIMIT 1
    );

SET
    @config_textarea_field_id = (
        SELECT id
        FROM lu_config_field
        WHERE
            field = 'textarea'
        LIMIT 1
    );

-- Get category and subcategory ids for new skin config settings
SET
    @category_colours = (
        SELECT id
        FROM lu_skin_config_category
        WHERE
            name = 'Colours'
    );

SET
    @category_css = (
        SELECT id
        FROM lu_skin_config_category
        WHERE
            name = 'Custom CSS'
    );

SET
    @category_roundness = (
        SELECT id
        FROM lu_skin_config_category
        WHERE
            name = 'Roundness'
    );

SET
    @category_drop_shadow = (
        SELECT id
        FROM lu_skin_config_category
        WHERE
            name = 'Drop shadow'
    );

SET
    @subcategory_menu = (
        SELECT id
        FROM lu_skin_config_subcategory
        WHERE
            category_id = @category_colours
            AND name = 'Menu'
    );

SET
    @subcategory_general = (
        SELECT id
        FROM lu_skin_config_subcategory
        WHERE
            category_id = @category_colours
            AND name = 'General'
    );

SET
    @subcategory_component = (
        SELECT id
        FROM lu_skin_config_subcategory
        WHERE
            category_id = @category_colours
            AND name = 'Component'
    );

SET
    @subcategory_login = (
        SELECT id
        FROM lu_skin_config_subcategory
        WHERE
            category_id = @category_colours
            AND name = 'Login'
    );

SET
    @subcategory_css = (
        SELECT id
        FROM lu_skin_config_subcategory
        WHERE
            category_id = @category_css
            AND name IS NULL
    );

SET
    @subcategory_roundness = (
        SELECT id
        FROM lu_skin_config_subcategory
        WHERE
            category_id = @category_roundness
            AND name IS NULL
    );

SET
    @subcategory_drop_shadow = (
        SELECT id
        FROM lu_skin_config_subcategory
        WHERE
            category_id = @category_drop_shadow
            AND name IS NULL
    );

-- Get current values for skin config settings to be updated, to ensure no loss of customisation for existing instances.
SET
    @value_skin_colour_body_background = (
        SELECT `value`
        FROM config
        WHERE
            name = 'skin_colour_body_bg'
        LIMIT 1
    );

SET
    @value_skin_colour_accent = (
        SELECT `value`
        FROM config
        WHERE
            name = 'skin_colour_form_input_border_color'
        LIMIT 1
    );

SET
    @value_skin_colour_active = (
        SELECT `value`
        FROM config
        WHERE
            name = 'skin_colour_content_ui_selected'
        LIMIT 1
    );

SET
    @value_skin_colour_menu_background = (
        SELECT `value`
        FROM config
        WHERE
            name = 'skin_colour_navigation_background'
        LIMIT 1
    );

SET
    @value_skin_colour_menu_foreground = (
        SELECT `value`
        FROM config
        WHERE
            name = 'skin_colour_navigation_foreground'
        LIMIT 1
    );

SET
    @value_skin_colour_menu_hover = (
        SELECT `value`
        FROM config
        WHERE
            name = 'skin_colour_navigation_hover'
        LIMIT 1
    );

SET
    @value_skin_colour_component_heading_text = (
        SELECT `value`
        FROM config
        WHERE
            name = 'homepage_colour_head_font'
        LIMIT 1
    );

SET
    @value_skin_colour_component_heading_background = (
        SELECT `value`
        FROM config
        WHERE
            name = 'homepage_colour_head_bg'
        LIMIT 1
    );

SET
    @value_skin_colour_sonar_login_background = (
        SELECT `value`
        FROM config
        WHERE
            name = 'skin_colour_login_background'
        LIMIT 1
    );

SET
    @value_skin_sonar_custom_css = (
        SELECT `value`
        FROM config
        WHERE
            name = 'skin_custom_css'
        LIMIT 1
    );

SET
    @settings_sequence_start = (
        SELECT COALESCE(MAX(sequence), 0)
        FROM config
        WHERE
            module = 'settings'
    );

-- Inserting the new skin config settings
INSERT INTO
    config (
        name,
        value,
        title,
        description,
        field_id,
        type_id,
        editable,
        hidden,
        required,
        module,
        sequence,
        options
    )
VALUES (
        'skin_colour_menu_background',
        @value_skin_colour_menu_background,
        'Menu Background Colour',
        'The background colour of the top menu.',
        @config_colour_field_id,
        @sonar_config_type_id,
        'true',
        'false',
        'false',
        'settings',
        @settings_sequence_start + 1,
        '{"customProperty":"--sbx-config-color-menu-background","contrast":"--sbx-config-color-menu-foreground"}'
    ),
    (
        'skin_colour_menu_foreground',
        @value_skin_colour_menu_foreground,
        'Menu Foreground Colour',
        'The foreground colour of the top menu.',
        @config_colour_field_id,
        @sonar_config_type_id,
        'true',
        'false',
        'false',
        'settings',
        @settings_sequence_start + 2,
        '{"customProperty":"--sbx-config-color-menu-foreground","contrast":"--sbx-config-color-menu-background"}'
    ),
    (
        'skin_colour_menu_hover',
        @value_skin_colour_menu_hover,
        'Menu Hover Colour',
        'The background colour of the element when the mouse is hovering over a menu element.',
        @config_colour_field_id,
        @sonar_config_type_id,
        'true',
        'false',
        'false',
        'settings',
        @settings_sequence_start + 3,
        '{"customProperty":"--sbx-config-color-menu-hover","contrast":"--sbx-config-color-menu-foreground"}'
    ),
    (
        'skin_colour_body_background',
        @value_skin_colour_body_background,
        'Body Background Colour',
        'The background colour of every page.',
        @config_colour_field_id,
        @sonar_config_type_id,
        'true',
        'false',
        'false',
        'settings',
        @settings_sequence_start + 4,
        '{"customProperty":"--sbx-config-color-body-background","contrast":"--sbx-color-text-primary"}'
    ),
    (
        'skin_colour_accent',
        @value_skin_colour_accent,
        'Accent Colour',
        'The colour used in interactive elements like buttons, links and check boxes.',
        @config_colour_field_id,
        @sonar_config_type_id,
        'true',
        'false',
        'false',
        'settings',
        @settings_sequence_start + 5,
        '{"customProperty":"--sbx-config-color-accent","contrast":"--sbx-color-foreground-primary"}'
    ),
    (
        'skin_colour_accent_text',
        '#ffffff',
        'Accent Text Colour',
        'The text colour for when displayed over the Accent Colour.',
        @config_colour_field_id,
        @sonar_config_type_id,
        'true',
        'false',
        'false',
        'settings',
        @settings_sequence_start + 6,
        '{"customProperty":"--sbx-config-color-accent-text","contrast":"--sbx-config-color-accent"}'
    ),
    (
        'skin_colour_active',
        @value_skin_colour_active,
        'Active Colour',
        'The colour used to indicate active elements, like active filters, selected menu items and hover states.',
        @config_colour_field_id,
        @sonar_config_type_id,
        'true',
        'false',
        'false',
        'settings',
        @settings_sequence_start + 7,
        '{"customProperty":"--sbx-config-color-active","contrast":"--sbx-config-color-accent"}'
    ),
    (
        'skin_colour_component_heading_background',
        @value_skin_colour_component_heading_background,
        'Component Heading Background Colour',
        'The colour of the heading background on homepage components.',
        @config_colour_field_id,
        @sonar_config_type_id,
        'true',
        'false',
        'false',
        'settings',
        @settings_sequence_start + 8,
        '{"customProperty":"--sbx-config-color-component-heading-background","contrast":"--sbx-config-color-component-heading-text"}'
    ),
    (
        'skin_colour_component_heading_text',
        @value_skin_colour_component_heading_text,
        'Component Heading Text Colour',
        'The colour of the heading text on homepage components.',
        @config_colour_field_id,
        @sonar_config_type_id,
        'true',
        'false',
        'false',
        'settings',
        @settings_sequence_start + 9,
        '{"customProperty":"--sbx-config-color-component-heading-text","contrast":"--sbx-config-color-component-heading-background"}'
    ),
    (
        'skin_colour_sonar_login_background',
        @value_skin_colour_sonar_login_background,
        'Login Background Colour',
        'The background colour of the login form on the login page.',
        @config_colour_field_id,
        @sonar_config_type_id,
        'true',
        'false',
        'false',
        'settings',
        @settings_sequence_start + 10,
        '{"customProperty":"--sbx-config-login-form-background","contrast":"--sbx-config-login-form-foreground"}'
    ),
    (
        'skin_colour_sonar_login_foreground',
        '#ffffff',
        'Login Foreground Colour',
        'The foreground colour of the login form on the login page.',
        @config_colour_field_id,
        @sonar_config_type_id,
        'true',
        'false',
        'false',
        'settings',
        @settings_sequence_start + 11,
        '{"customProperty":"--sbx-config-login-form-foreground","contrast":"--sbx-config-login-form-background"}'
    ),
    (
        'skin_field_roundness',
        '0.5rem',
        'Field Roundness',
        'Add rounded corners to elements like check boxes, buttons and labels.',
        @config_select_field_id,
        @sonar_config_type_id,
        'true',
        'false',
        'false',
        'settings',
        @settings_sequence_start + 12,
        '{"customProperty":"--sbx-config-field-roundness","select":"none:small:medium:large"}'
    ),
    (
        'skin_box_roundness',
        '0.5rem',
        'Box Roundness',
        'Add rounded corners to primary container elements.',
        @config_select_field_id,
        @sonar_config_type_id,
        'true',
        'false',
        'false',
        'settings',
        @settings_sequence_start + 13,
        '{"customProperty":"--sbx-config-box-roundness","select":"none:small:medium:large"}'
    ),
    (
        'skin_box_drop_shadow',
        '0',
        'Box Drop Shadow',
        'Add a drop shadow to primary container elements.',
        @config_checkbox_field_id,
        @sonar_config_type_id,
        'true',
        'false',
        'false',
        'settings',
        @settings_sequence_start + 14,
        NULL
    ),
    (
        'skin_sonar_custom_css',
        @value_skin_sonar_custom_css,
        'Custom CSS',
        'Insert custom CSS to override internal styles. Please be aware that custom CSS can break the appearance of your instance, ensure your CSS is valid before proceeding. Also note that changes applied here will not also be applied when the Sonar UI toggle is off.',
        @config_textarea_field_id,
        @sonar_config_type_id,
        'true',
        'false',
        'false',
        'settings',
        @settings_sequence_start + 15,
        NULL
    );
-- Append them to the relevant skin config categories/subcategories
INSERT INTO
    skin_config_category (configID, subcategory_id)
VALUES (
        (
            SELECT configID
            FROM config
            WHERE
                name = 'skin_colour_body_background'
        ),
        @subcategory_general
    ),
    (
        (
            SELECT configID
            FROM config
            WHERE
                name = 'skin_colour_accent'
        ),
        @subcategory_general
    ),
    (
        (
            SELECT configID
            FROM config
            WHERE
                name = 'skin_colour_accent_text'
        ),
        @subcategory_general
    ),
    (
        (
            SELECT configID
            FROM config
            WHERE
                name = 'skin_colour_active'
        ),
        @subcategory_general
    ),
    (
        (
            SELECT configID
            FROM config
            WHERE
                name = 'skin_colour_menu_background'
        ),
        @subcategory_menu
    ),
    (
        (
            SELECT configID
            FROM config
            WHERE
                name = 'skin_colour_menu_foreground'
        ),
        @subcategory_menu
    ),
    (
        (
            SELECT configID
            FROM config
            WHERE
                name = 'skin_colour_menu_hover'
        ),
        @subcategory_menu
    ),
    (
        (
            SELECT configID
            FROM config
            WHERE
                name = 'skin_colour_component_heading_text'
        ),
        @subcategory_component
    ),
    (
        (
            SELECT configID
            FROM config
            WHERE
                name = 'skin_colour_component_heading_background'
        ),
        @subcategory_component
    ),
    (
        (
            SELECT configID
            FROM config
            WHERE
                name = 'skin_colour_sonar_login_background'
        ),
        @subcategory_login
    ),
    (
        (
            SELECT configID
            FROM config
            WHERE
                name = 'skin_colour_sonar_login_foreground'
        ),
        @subcategory_login
    ),
    (
        (
            SELECT configID
            FROM config
            WHERE
                name = 'skin_field_roundness'
        ),
        @subcategory_roundness
    ),
    (
        (
            SELECT configID
            FROM config
            WHERE
                name = 'skin_box_roundness'
        ),
        @subcategory_roundness
    ),
    (
        (
            SELECT configID
            FROM config
            WHERE
                name = 'skin_box_drop_shadow'
        ),
        @subcategory_drop_shadow
    ),
    (
        (
            SELECT configID
            FROM config
            WHERE
                name = 'skin_sonar_custom_css'
        ),
        @subcategory_css
    );
