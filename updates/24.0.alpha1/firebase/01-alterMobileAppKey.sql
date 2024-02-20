# Allow mobile app key to be a full text value,
# so that it may accommodate a JSON value from Firebase.
# Note that this updates the charset from ascii to utf8mb4.
alter table mobile_app modify `key` TEXT not null;
