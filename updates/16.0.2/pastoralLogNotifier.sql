-- Create a stored procedure to create the message route permissions

DELIMITER ;
DROP PROCEDURE IF EXISTS create_route_perms;

DELIMITER //
CREATE PROCEDURE create_route_perms(IN source INT, IN grouptype VARCHAR(255))
  BEGIN

    DECLARE role_id INT DEFAULT 0;
    DECLARE cursor_finished INT DEFAULT 0;
    DECLARE cursor_roles CURSOR FOR SELECT `id` FROM `groups` WHERE `group_type` = grouptype;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET cursor_finished = 1;

    OPEN cursor_roles;

    role_loop: LOOP

      FETCH cursor_roles INTO role_id;

      IF cursor_finished = 1 THEN
        LEAVE role_loop;
      END IF;

      INSERT INTO msg_route_perm (msg_source_id, role_id, mask, role_default)
      VALUES (source, role_id, 'MU,DI,IN,MO,EM', 'IN');

    END LOOP;

  END
//

DELIMITER ;

-- Delete any previous created pastoral notification stuff

DELETE FROM msg_route_perm WHERE msg_source_id in (SELECT `id` FROM msg_source WHERE namespace in (SELECT `id` FROM msg_namespace WHERE slug = 'pastoral_log'));
DELETE FROM msg_source WHERE namespace in (SELECT `id` FROM msg_namespace WHERE slug = 'pastoral_log');
DELETE FROM msg_namespace WHERE slug = 'pastoral_log';

-- Create notification namespace and get its ID

INSERT INTO msg_namespace (slug, label) VALUES ('pastoral_log', 'Pastoral Care Import Notification');
SET @namespace = LAST_INSERT_ID();

-- Staff create log notification

INSERT INTO msg_source (`namespace`, `key`, `role_type`, `description`) VALUES (@namespace, 'created', 'staff', 'a pastoral care record import has completed.');
SET @source = LAST_INSERT_ID();
CALL create_route_perms(@source, 'staff');

-- Get rid of the stored procedure after everything created as it really serves no purpose anymore

DROP PROCEDURE IF EXISTS create_route_perms;
