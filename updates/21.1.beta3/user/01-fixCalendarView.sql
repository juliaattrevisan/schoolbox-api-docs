# Somehow: some users have a calendar view which isn't in the enum.
# Set it to the default value (month).
UPDATE user SET calendar_view = 'month'
WHERE calendar_view NOT IN ('month','agendaWeek','agendaDay','list');
