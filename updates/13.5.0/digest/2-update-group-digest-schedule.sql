-- Populate based on the digest email config settings
-- Daily setting clobbers weekly, because daily digest would include all weekly
UPDATE `groups` SET `digest_schedule` = IF(
    (SELECT `value` FROM `config` WHERE `name` = 'digest_send_weekly'),
    1, 0
);
UPDATE `groups` SET `digest_schedule` = IF(
    (SELECT `value` FROM `config` WHERE `name` = 'digest_send_daily'),
    2, 0
);
