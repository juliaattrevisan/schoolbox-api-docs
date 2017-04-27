/* Adds the reply-to config (defaults to off) */
INSERT INTO config VALUES (
    NULL, 'mail_replyto_enabled', 0, 'Enable Email Reply-To',
    'If ticked, notification emails will populate the reply-to address of the author, where relevant',
    3, 'true:false', 8, 'true', 'false', 'false'
);
