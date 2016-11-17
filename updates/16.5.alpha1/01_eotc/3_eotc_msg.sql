/* Adding message settings
*/
drop procedure if exists check_msg;
delimiter //
create procedure check_msg()
  begin

    set @namespace := (SELECT id FROM `msg_namespace` WHERE  slug = 'eotc');

    if @namespace is null then
      INSERT INTO `msg_namespace`
      (`id`,`slug`,`label`)
      VALUES
        (NULL, 'eotc', 'EOTC')
      ON DUPLICATE KEY update `id` = `id`;
      set @namespace := LAST_INSERT_ID();
    end if;

    set @source1 := (SELECT id FROM `msg_source` WHERE  namespace = @namespace and description = 'someone submits an EOTC');
    set @source2 := (SELECT id FROM `msg_source` WHERE  namespace = @namespace and description = 'a moderator changes the status of your EOTC');
    /*There is a typo in 'coments'*/
    set @source3 := (SELECT id FROM `msg_source` WHERE  namespace = @namespace and description = 'someone updates or coments on your EOTC');
    set @source31 := (SELECT id FROM `msg_source` WHERE  namespace = @namespace and description = 'someone updates or comments on your EOTC');
    set @source4 := (SELECT id FROM `msg_source` WHERE  namespace = @namespace and description = 'an EOTC is upcoming and requires your approval');

    if @source1 is null then
      INSERT INTO `msg_source`
      (`namespace`, `key`, `role_type`, `description`)
      VALUES
        (@namespace, 'submitted', 'staff', 'someone submits an EOTC')
      ON DUPLICATE KEY UPDATE `id` = `id`;
      set @source1 := LAST_INSERT_ID();
    end if;

    if @source2 is null then
      INSERT INTO `msg_source`
      (`namespace`, `key`, `role_type`, `description`)
      VALUES
        (@namespace, 'statuschanged', 'staff', 'a moderator changes the status of your EOTC')
        ON DUPLICATE KEY UPDATE `id` = `id`;
      set @source2 := LAST_INSERT_ID();
    end if;

    if @source3 is null and @source31 is null then
      INSERT INTO `msg_source`
      (`namespace`, `key`, `role_type`, `description`)
      VALUES
        (@namespace, 'updated', 'staff', 'someone updates or comments on your EOTC')
        ON DUPLICATE KEY UPDATE `id` = `id`;
      set @source3 := LAST_INSERT_ID();
    elseif @source3 is not null then
      /*updating typo*/
      UPDATE `msg_source` SET `description` = 'someone updates or comments on your EOTC' WHERE id = @source3;
    elseif @source31 is not null then
      set @source3 := @source31;
    end if;

    if @source4 is null then
      INSERT INTO `msg_source`
      (`namespace`, `key`, `role_type`, `description`)
      VALUES
        (@namespace, 'upcoming', 'staff', 'an EOTC is upcoming and requires your approval')
        ON DUPLICATE KEY UPDATE `id` = `id`;
      set @source4 := LAST_INSERT_ID();
    end if;

    set @perm1 := (SELECT `msg_source_id` FROM `msg_route_perm` WHERE `msg_source_id` = @source1 GROUP BY `msg_source_id`);
    set @perm2 := (SELECT `msg_source_id` FROM `msg_route_perm` WHERE `msg_source_id` = @source2 GROUP BY `msg_source_id`);
    set @perm3 := (SELECT `msg_source_id` FROM `msg_route_perm` WHERE `msg_source_id` = @source3 GROUP BY `msg_source_id`);
    set @perm4 := (SELECT `msg_source_id` FROM `msg_route_perm` WHERE `msg_source_id` = @source4 GROUP BY `msg_source_id`);

    if @perm1 is null then

      INSERT INTO `msg_route_perm` (`msg_source_id`, `role_id`, `mask`, `role_default`)
        SELECT src.id `msg_source_id`, g.id `role_id`, 'MU,DI,IN,MO,EM' `mask`, 'IN,EM' `role_default`
        FROM `msg_source` src
          LEFT JOIN `groups` g ON src.`role_type` = g.`group_type`
          WHERE src.id = @source1
      ON DUPLICATE KEY UPDATE `msg_source_id` = `msg_source_id`;

    end if;

    if @perm2 is null then

      INSERT INTO `msg_route_perm` (`msg_source_id`, `role_id`, `mask`, `role_default`)
        SELECT src.id `msg_source_id`, g.id `role_id`, 'MU,DI,IN,MO,EM' `mask`, 'IN,EM' `role_default`
        FROM `msg_source` src
          LEFT JOIN `groups` g ON src.`role_type` = g.`group_type`
        WHERE src.id = @source2
      ON DUPLICATE KEY UPDATE `msg_source_id` = `msg_source_id`;

    end if;

    if @perm3 is null then

      INSERT INTO `msg_route_perm` (`msg_source_id`, `role_id`, `mask`, `role_default`)
        SELECT src.id `msg_source_id`, g.id `role_id`, 'MU,DI,IN,MO,EM' `mask`, 'IN,EM' `role_default`
        FROM `msg_source` src
          LEFT JOIN `groups` g ON src.`role_type` = g.`group_type`
        WHERE src.id = @source3
      ON DUPLICATE KEY UPDATE `msg_source_id` = `msg_source_id`;

    end if;

    if @perm4 is null then

      INSERT INTO `msg_route_perm` (`msg_source_id`, `role_id`, `mask`, `role_default`)
        SELECT src.id `msg_source_id`, g.id `role_id`, 'MU,DI,IN,MO,EM' `mask`, 'IN,EM' `role_default`
        FROM `msg_source` src
          LEFT JOIN `groups` g ON src.`role_type` = g.`group_type`
        WHERE src.id = @source4
      ON DUPLICATE KEY UPDATE `msg_source_id` = `msg_source_id`;

    end if;

  end;
//
delimiter ;
call check_msg();
drop procedure check_msg;