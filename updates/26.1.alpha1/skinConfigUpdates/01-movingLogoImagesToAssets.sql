-- Creates the new Assets config type and moves all logo images to it.
-- Hard-code the ID to match ConfigMapper::TYPE_ASSETS.
INSERT INTO lu_config_type (id, type, sequence)
VALUES (
    27,
    'Assets',
    (SELECT MAX(sequence) + 1 FROM lu_config_type)
);

-- Get the IDs for the Skin Config and Assets config types.
SET
    @skinConfigTypeId = (SELECT id
                         FROM lu_config_type
                         WHERE type = 'Skin Config');
SET @assetsTypeId = (SELECT id
                     FROM lu_config_type
                     WHERE type = 'Assets');

-- Move all logo images to the new Assets config type.
UPDATE config
SET type_id = @assetsTypeId
WHERE type_id = @skinConfigTypeId
  AND name IN
      (
       'skin_favicon',
       'skin_logo_square',
       'skin_logo_large',
       'skin_logo_login',
       'skin_login_bg',
       'email_banner_image'
          );
