ALTER TABLE resource
ADD COLUMN allow_clash_bookings tinyint(1) NOT NULL DEFAULT 0 AFTER `room_code`;
