/* Ensure calendar view is set to month and field defaults to month with no null allowed */
UPDATE user SET calendar_view = 'month' WHERE calendar_view = '' OR calendar_view IS NULL;
ALTER TABLE user MODIFY calendar_view ENUM('month','agendaWeek','agendaDay') NOT NULL DEFAULT 'month';
