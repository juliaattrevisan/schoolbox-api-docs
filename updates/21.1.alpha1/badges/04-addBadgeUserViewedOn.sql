/**
 * Add the `badge_user.viewed_on` column: This field will contain a date when the badge was first viewed_on.
 */
ALTER TABLE badge_user ADD viewed_on DATETIME NULL AFTER expires_at;
