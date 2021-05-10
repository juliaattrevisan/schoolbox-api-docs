/**
 * Set up a new primary key.
 *
 * The primary key was only on the first 255 chars of `app_token`:
 * that's how it was able to be created in the first place.
 */
ALTER TABLE mobile_app_registry DROP PRIMARY KEY;
ALTER TABLE mobile_app_registry ADD PRIMARY KEY (user_id, app_token(255), app_id);
