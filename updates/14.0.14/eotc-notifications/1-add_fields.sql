-- EOTC permissions:
--  is_allowed now controls whether they are actually allowed
--  is_subscribed controls whether this user gets notified when an event enters this status
ALTER TABLE `eotc_permission`
  ADD COLUMN `is_allowed` BOOL DEFAULT TRUE NOT NULL AFTER `status_id`,
  ADD COLUMN `is_subscribed` BOOL DEFAULT FALSE NOT NULL AFTER `is_allowed`;
