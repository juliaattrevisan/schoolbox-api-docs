/**
 * Looks like at some point around 15.6 to 16 we changed storage format to prepend a # char.
 * This script is intended to prepend a # char to any old-format records.
 */
UPDATE lu_timetable_colour
SET `hex_colour` = concat('#',`hex_colour`)
WHERE `hex_colour` NOT LIKE '#%';