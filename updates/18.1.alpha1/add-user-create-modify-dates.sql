# We need created_at and updated_at to not be null,
# but SQL won't let us set a default value using a function,
# so we need to hard-code these timestamps.

# We can't use an all-0 timestamp because it gets interpreted as null in the mapper
# and written back to the database on update,
# causing an integrity constraint violation.

# Not that the hard-coded epoch is not timezone-sensitive.

ALTER TABLE `user`
    ADD COLUMN `created_at`
    DATETIME NOT NULL
    DEFAULT '1970-01-01 00:00:00'
    AFTER `display_grade_graph`,

    ADD COLUMN `updated_at`
    DATETIME NOT NULL
    DEFAULT '1970-01-01 00:00:00'
    AFTER `created_at`,

    CHANGE COLUMN `deleted_at` `deleted_at`
    DATETIME NULL
    DEFAULT NULL
    AFTER `updated_at`;

# Slight hack: any existing users now get their timestamps updated to the actual timezone-sensitive epoch.
# (On insert, newly created users automagically get the current time as their creation/updation timestamp).
UPDATE `user` SET created_at=FROM_UNIXTIME(0), updated_at=FROM_UNIXTIME(0);