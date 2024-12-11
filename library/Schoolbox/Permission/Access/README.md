# User Access Permission System

Permissions are set on a user's profile page (scroll down to the PERMISSIONS section).

## Database schema

The services listed in the permissions section are defined in the lu_service table.
```
MariaDB [schoolbox]> select * from lu_service;
+----+------------------------+
| id | name                   |
+----+------------------------+
|  1 | Homepage               |
|  2 | Calendar               |
|  3 | News                   |
|  4 | Tutorial               |
|  5 | CMS                    |
|  7 | Resource Booking       |
|  8 | Curriculum             |
|  9 | Pastoral Moderator     |
| 10 | Fixtures Administrator |
+----+------------------------+
9 rows in set (0.001 sec)
```

Granted permissions are stored in the userAccess table:
```
MariaDB [schoolbox]> show create table userAccess\G
*************************** 1. row ***************************
       Table: userAccess
Create Table: CREATE TABLE `userAccess` (
  `uid` int(11) unsigned NOT NULL,
  `service` int(11) unsigned NOT NULL,
  `sid` int(11) NOT NULL DEFAULT 0,
  `type` smallint(6) DEFAULT NULL,
  `favourite` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`service`,`sid`,`uid`),
  KEY `uid` (`uid`),
  KEY `sid` (`sid`),
  KEY `service` (`service`),
  KEY `idx_userAccess_type` (`type`),
  CONSTRAINT `fk_userAccess_service_id` FOREIGN KEY (`service`) REFERENCES `lu_service` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_userAccess_user_id` FOREIGN KEY (`uid`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
1 row in set (0.000 sec)
```
Where:
 * 'uid' refers to a user as shown by the foreign key above.
 * 'service' refers to lu_service as shown by the foreign key above.
 * 'sid' refers to different things based on the value of 'service':
    * for 'service'=1 (Homepage), 'sid' refers to folder.id
    * for 'service'=2 (Calendar), 'sid' refers to luCampus.id
    * for 'service'=15 (Impersonator), 'sid' refers to groups.id (also known as roleId)
    * for other values of 'service', 'sid' is ignored (but should always be 0).
  * 'type' refers to the type of access (see UserAccess.php):
    * 0 => none
    * 1 => read-level access
    * 2 => write-level access
    * 3 => full/moderate-level access - this allows users write-level access, plus the ability to
           modify/delete other user’s content.  Also moderate-level access grants access to related admin sections
 * 'favourite' determines whether this userAccess relationship should be included in a 'favourites' list.
   This currently only applies to 'service' 1, and is used to mark as favourites, a subset of groups the user has access to.
   E.g. See the /schoolbox/groups#my-favourite-groups page.
