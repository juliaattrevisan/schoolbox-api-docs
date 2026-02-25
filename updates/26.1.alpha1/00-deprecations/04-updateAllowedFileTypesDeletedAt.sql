UPDATE allowedFileTypes SET deleted_at = NULL WHERE deleted IS FALSE;
UPDATE allowedFileTypes SET deleted_at = NOW() WHERE deleted IS TRUE;
