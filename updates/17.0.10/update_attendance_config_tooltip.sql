UPDATE config
SET description = 'Allows teachers to mark attendance online, only compatible with Synergetic and TASS.'
WHERE name = 'external_attendance'
and description = 'Allows teachers to mark attendance online, only compatible with Synergetic.';