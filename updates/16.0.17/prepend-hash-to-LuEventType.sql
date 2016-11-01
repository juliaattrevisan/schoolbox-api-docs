UPDATE luEventType
SET `eventColour` = concat('#',`eventColour`)
WHERE `eventColour` NOT LIKE '#%';