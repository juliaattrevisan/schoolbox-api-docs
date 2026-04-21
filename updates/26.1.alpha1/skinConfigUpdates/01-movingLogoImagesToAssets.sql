-- Creates the new Assets config type and moves all logo images to it.
-- Hard-code the ID to match ConfigMapper::TYPE_ASSETS.
SET @assetsTypeId = 27;
INSERT INTO lu_config_type (id, type, sequence)
VALUES (
    @assetsTypeId,
    'Assets',
    (SELECT MAX(sequence) + 1 FROM lu_config_type)
);

-- Get the ID for the Assets config type.
SET
    @skinConfigTypeId = (SELECT id
                         FROM lu_config_type
                         WHERE type = 'Skin Config');

-- Move all logo images to the Skin Config type.
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
