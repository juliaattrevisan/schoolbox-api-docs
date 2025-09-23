
ALTER TABLE profile_field
ADD COLUMN `options` JSON NULL AFTER css;

ALTER TABLE profile_field
RENAME COLUMN click_to_reveal TO _click_to_reveal;

UPDATE profile_field
SET `options` = '{"iframeLink": "", "iframeHeight": 300}'
WHERE field = 'iframe';

UPDATE profile_field
SET `options` = concat('{"clickToReveal": ', _click_to_reveal, '}')
WHERE
    ((icon IS NOT NULL and icon != '') AND (css IS NOT NULL and css != ''))
    OR
    (_click_to_reveal != 0);
