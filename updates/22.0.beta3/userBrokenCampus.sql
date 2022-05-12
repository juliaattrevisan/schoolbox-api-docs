#fix broken users, if they have no campus, give them all active campus'.
INSERT INTO userCampus (uid,campus)
SELECT user.id, luCampus.id
FROM user
         JOIN luCampus
WHERE
    luCampus.deleted_at is null
  and
        user.id in (
        SELECT id
        FROM user
        WHERE
                user.id not in (SELECT uid FROM userCampus)
          AND
            deleted_at is null
    )
