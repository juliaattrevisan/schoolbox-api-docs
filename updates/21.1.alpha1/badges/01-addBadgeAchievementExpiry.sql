/**
 * Add the `badge.achievement_expiry` column: this represents a number of days
 * after which an achievement awarded for this badge expires.
 *
 * This doesn't apply when setting this badge as a goal: it's likely a separate
 * field will be added to control goal expiry.
 */
ALTER TABLE badge ADD achievement_expiry INT UNSIGNED NULL AFTER category_id;
