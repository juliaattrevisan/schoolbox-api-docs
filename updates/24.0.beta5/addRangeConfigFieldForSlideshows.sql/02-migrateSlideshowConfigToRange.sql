-- The image slideshow max config field for the resources type is now a range to restrict it from 1 to 20
UPDATE `config`
SET
    `field_id` = 20,
    `options` = '{"min": 1, "max": 20, "step": 1}',
    `description` = "Select the maximum number of images displayed per sldieshow component, from 1 to 20."
WHERE
    `name` = "image_slideshow_max";

-- Restrict the value of existing configs to the new range
UPDATE `config`
SET
    `value` = "20"
WHERE
    `name` = "image_slideshow_max"
    AND (
        `value` = ""
        OR `value` < 1
        OR `value` > 20
    );
