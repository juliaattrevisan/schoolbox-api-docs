ALTER TABLE resource
ADD COLUMN capacity int(11) NOT NULL DEFAULT 1 AFTER `allow_clash_bookings`;
