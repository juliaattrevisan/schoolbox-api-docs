ALTER TABLE event_attendance
ADD UNIQUE(event_id, user_id);