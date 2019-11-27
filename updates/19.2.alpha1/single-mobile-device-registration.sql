/* First lets cleanup all registrations that haven't been logged into in the last 2 years */
DELETE FROM mobile_app_registry WHERE touched_at < '2018-01-01';

/* Not lets cleanup any duplicate registrations on the same device, leaving the most recently used */
DELETE mar1 FROM mobile_app_registry mar1
    INNER JOIN mobile_app_registry mar2 ON mar1.user_id != mar2.user_id
    AND mar1.touched_at < mar2.touched_at AND mar1.app_token = mar2.app_token;

/* Removing this index, but unable to add app_token unique index because of its size */
ALTER TABLE `mobile_app_registry`
    DROP INDEX `user_id_2`;
