/**
 * Add the `badge.goal_expiry` column: this represents a number of days after
  which a goal awarded for this badge expires.
 */
ALTER TABLE badge ADD goal_expiry INT UNSIGNED NULL AFTER achievement_expiry;
