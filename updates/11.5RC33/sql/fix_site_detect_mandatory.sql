 /* Fixed bug where site detect was mandatory. */
UPDATE config SET editable='false' WHERE name='site_detect';