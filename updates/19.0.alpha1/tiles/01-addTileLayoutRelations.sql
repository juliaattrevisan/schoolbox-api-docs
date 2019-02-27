-- Now, add the relation tables
-- (These remain CASCADEs as they're M-M relations without additional data)


-- Tiles for folders
CREATE TABLE tile_layout_for_folder (
    tile_layout_id INT(11) UNSIGNED NOT NULL,
    folder_id      INT(11) UNSIGNED NOT NULL,
    UNIQUE tile_layout_for_folder_unique (tile_layout_id, folder_id),
    KEY fk_tile_layout_for_folder_tile_layout_id (tile_layout_id),
    KEY fk_tile_layout_for_folder_folder_id      (folder_id),
    CONSTRAINT fk_tile_layout_for_folder_tile_layout_id
        FOREIGN KEY (tile_layout_id) REFERENCES tile_layout (id)
            ON DELETE CASCADE
            ON UPDATE CASCADE,
    CONSTRAINT fk_tile_layout_for_folder_folder_id
        FOREIGN KEY (folder_id) REFERENCES folder (id)
            ON DELETE CASCADE
            ON UPDATE CASCADE
);


-- Tiles for homepage templates
CREATE TABLE tile_layout_for_homepage_template (
    tile_layout_id       INT(11) UNSIGNED NOT NULL,
    homepage_template_id INT(11) UNSIGNED NOT NULL,
    UNIQUE tile_layout_for_homepage_template_unique (tile_layout_id, homepage_template_id),
    KEY fk_tile_layout_for_homepage_template_tile_layout_id       (tile_layout_id),
    KEY fk_tile_layout_for_homepage_template_homepage_template_id (homepage_template_id),
    CONSTRAINT fk_tile_layout_for_homepage_template_tile_layout_id
        FOREIGN KEY (tile_layout_id) REFERENCES tile_layout (id)
            ON DELETE CASCADE
            ON UPDATE CASCADE,
    CONSTRAINT fk_tile_layout_for_homepage_template_homepage_template_id
        FOREIGN KEY (homepage_template_id) REFERENCES homepageTemplate (id)
            ON DELETE CASCADE
            ON UPDATE CASCADE
);


-- Tiles for dashboards
-- role ID remains INT(4) due to the number of FKs I'd have to update
CREATE TABLE tile_layout_for_dashboard (
    tile_layout_id INT(11) UNSIGNED NOT NULL,
    role_id        INT(4)  UNSIGNED NOT NULL,
    UNIQUE tile_layout_for_dashboard_unique (tile_layout_id, role_id),
    KEY fk_tile_layout_for_dashboard_tile_layout_id (tile_layout_id),
    KEY fk_tile_layout_for_dashboard_role_id        (role_id),
    CONSTRAINT fk_tile_layout_for_dashboard_tile_layout_id
        FOREIGN KEY (tile_layout_id) REFERENCES tile_layout (id)
            ON DELETE CASCADE
            ON UPDATE CASCADE,
    CONSTRAINT fk_tile_layout_for_dashboard_role_id
        FOREIGN KEY (role_id) REFERENCES groups (id)
            ON DELETE CASCADE
            ON UPDATE CASCADE
);


-- Tile layouts now need headings, so that their name can show in dropdowns.
ALTER TABLE tile_layout ADD COLUMN heading VARCHAR(100) NOT NULL AFTER id;
