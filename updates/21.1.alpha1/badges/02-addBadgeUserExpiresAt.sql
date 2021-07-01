/**
 * Add the `badge_user.expires_at` column: this represents a date on which the
 * actual awarded achievement will expire.
 *
 * When an achievement is awarded: the expires_at will be set to the current
 * date + `badge.achievement_expiry` days, or `NULL` if the badge has no expiry.
 *
 * This field doesn't yet get set for goals.
 */
 ALTER TABLE badge_user ADD expires_at DATE NULL AFTER awarded_at;
