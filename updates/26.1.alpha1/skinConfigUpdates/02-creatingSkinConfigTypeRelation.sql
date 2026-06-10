-- Create lu_skin_config_subcategory and lu_skin_config_category tables
-- to store skin configuration types and sections for better
-- organization of skin configuration settings in the database.
CREATE TABLE lu_skin_config_category (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL
);

INSERT INTO
    lu_skin_config_category (`name`)
VALUES ('Colours'),
    ('Custom CSS'),
    ('Corner Radius'),
    ('Drop Shadow');

CREATE TABLE lu_skin_config_subcategory (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category_id INT NOT NULL,
    name VARCHAR(100),
    KEY idx_skin_config_category_category_id (category_id),
    CONSTRAINT fk_skin_config_category_category FOREIGN KEY (category_id) REFERENCES lu_skin_config_category (id)
);

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
    @category_corner_radius = (
        SELECT id
        FROM lu_skin_config_category
        WHERE
            name = 'Corner Radius'
    );

SET
    @category_drop_shadow = (
        SELECT id
        FROM lu_skin_config_category
        WHERE
            name = 'Drop Shadow'
    );

INSERT INTO
    lu_skin_config_subcategory (category_id, name)
VALUES (@category_colours, 'Menu'),
    (@category_colours, 'General'),
    (@category_colours, 'Top Menu'),
    (
        @category_colours,
        'Side Menu and Notification'
    ),
    (
        @category_colours,
        'User Account'
    ),
    (
        @category_colours,
        'Component'
    ),
    (
        @category_colours,
        'Interactive'
    ),
    (@category_colours, 'Login'),
    (@category_colours, 'Form'),
    (@category_css, null),
    (@category_corner_radius, null),
    (@category_drop_shadow, null);

SET
    @subcategory_general = (
        SELECT id
        FROM lu_skin_config_subcategory
        WHERE
            name = 'General'
    );

SET
    @subcategory_top_menu = (
        SELECT id
        FROM lu_skin_config_subcategory
        WHERE
            name = 'Top Menu'
    );

SET
    @subcategory_side_menu = (
        SELECT id
        FROM lu_skin_config_subcategory
        WHERE
            name = 'Side Menu and Notification'
    );

SET
    @subcategory_user_account = (
        SELECT id
        FROM lu_skin_config_subcategory
        WHERE
            name = 'User Account'
    );

SET
    @subcategory_component = (
        SELECT id
        FROM lu_skin_config_subcategory
        WHERE
            name = 'Component'
    );

SET
    @subcategory_interactive = (
        SELECT id
        FROM lu_skin_config_subcategory
        WHERE
            name = 'Interactive'
    );

SET
    @subcategory_form = (
        SELECT id
        FROM lu_skin_config_subcategory
        WHERE
            name = 'Form'
    );

SET
    @subcategory_login = (
        SELECT id
        FROM lu_skin_config_subcategory
        WHERE
            name = 'Login'
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
    @subcategory_corner_radius = (
        SELECT id
        FROM lu_skin_config_subcategory
        WHERE
            category_id = @category_corner_radius
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

CREATE TABLE skin_config_category (
    configID INT UNSIGNED,
    subcategory_id INT NOT NULL,
    KEY idx_skin_config_category_configID (configID),
    KEY idx_skin_config_category_subcategoryId (subcategory_id),
    CONSTRAINT fk_skin_config_category_config FOREIGN KEY (configID) REFERENCES config (configID),
    CONSTRAINT fk_skin_config_category_subcategory FOREIGN KEY (subcategory_id) REFERENCES lu_skin_config_subcategory (id)
);

INSERT INTO
    skin_config_category (configID, subcategory_id)
VALUES (
        (
            SELECT configID
            FROM config
            WHERE
                name = 'homepage_colour_head_font'
        ),
        @subcategory_component
    ),
    (
        (
            SELECT configID
            FROM config
            WHERE
                name = 'homepage_colour_head_bg'
        ),
        @subcategory_component
    ),
    (
        (
            SELECT configID
            FROM config
            WHERE
                name = 'homepage_colour_font'
        ),
        @subcategory_component
    ),
    (
        (
            SELECT configID
            FROM config
            WHERE
                name = 'homepage_colour_bg_primary'
        ),
        @subcategory_component
    ),
    (
        (
            SELECT configID
            FROM config
            WHERE
                name = 'skin_colour_navigation_foreground'
        ),
        @subcategory_top_menu
    ),
    (
        (
            SELECT configID
            FROM config
            WHERE
                name = 'skin_colour_navigation_background'
        ),
        @subcategory_top_menu
    ),
    (
        (
            SELECT configID
            FROM config
            WHERE
                name = 'skin_colour_navigation_hover'
        ),
        @subcategory_top_menu
    ),
    (
        (
            SELECT configID
            FROM config
            WHERE
                name = 'skin_colour_offcanvas_foreground'
        ),
        @subcategory_side_menu
    ),
    (
        (
            SELECT configID
            FROM config
            WHERE
                name = 'skin_colour_offcanvas_background'
        ),
        @subcategory_side_menu
    ),
    (
        (
            SELECT configID
            FROM config
            WHERE
                name = 'skin_colour_offcanvas_hover'
        ),
        @subcategory_side_menu
    ),
    (
        (
            SELECT configID
            FROM config
            WHERE
                name = 'skin_colour_logo_background'
        ),
        @subcategory_side_menu
    ),
    (
        (
            SELECT configID
            FROM config
            WHERE
                name = 'accounts_menu_bg_colour'
        ),
        @subcategory_user_account
    ),
    (
        (
            SELECT configID
            FROM config
            WHERE
                name = 'accounts_menu_text_colour'
        ),
        @subcategory_user_account
    ),
    (
        (
            SELECT configID
            FROM config
            WHERE
                name = 'accounts_menu_hover_colour'
        ),
        @subcategory_user_account
    ),
    (
        (
            SELECT configID
            FROM config
            WHERE
                name = 'skin_colour_body_bg'
        ),
        @subcategory_general
    ),
    (
        (
            SELECT configID
            FROM config
            WHERE
                name = 'skin_colour_login_background'
        ),
        @subcategory_general
    ),
    (
        (
            SELECT configID
            FROM config
            WHERE
                name = 'skin_colour_content_ui_submit_foreground'
        ),
        @subcategory_form
    ),
    (
        (
            SELECT configID
            FROM config
            WHERE
                name = 'skin_colour_content_ui_submit_background'
        ),
        @subcategory_form
    ),
    (
        (
            SELECT configID
            FROM config
            WHERE
                name = 'skin_colour_form_input_border_color'
        ),
        @subcategory_form
    ),
    (
        (
            SELECT configID
            FROM config
            WHERE
                name = 'skin_colour_form_background_hover_color'
        ),
        @subcategory_form
    ),
    (
        (
            SELECT configID
            FROM config
            WHERE
                name = 'skin_colour_content_ui_foreground'
        ),
        @subcategory_interactive
    ),
    (
        (
            SELECT configID
            FROM config
            WHERE
                name = 'skin_colour_content_ui_background'
        ),
        @subcategory_interactive
    ),
    (
        (
            SELECT configID
            FROM config
            WHERE
                name = 'skin_colour_content_ui_hover'
        ),
        @subcategory_interactive
    ),
    (
        (
            SELECT configID
            FROM config
            WHERE
                name = 'skin_colour_content_ui_selected'
        ),
        @subcategory_interactive
    ),
    (
        (
            SELECT configID
            FROM config
            WHERE
                name = 'skin_colour_accent_foreground'
        ),
        @subcategory_interactive
    ),
    (
        (
            SELECT configID
            FROM config
            WHERE
                name = 'skin_colour_accent_background'
        ),
        @subcategory_interactive
    ),
    (
        (
            SELECT configID
            FROM config
            WHERE
                name = 'skin_colour_accent_hover'
        ),
        @subcategory_interactive
    ),
    (
        (
            SELECT configID
            FROM config
            WHERE
                name = 'skin_custom_css'
        ),
        @subcategory_css
    );
