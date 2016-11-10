/* Add config setting if not already there
*/
delimiter //
create procedure check_config()
  begin

    set @identifier := (SELECT configId FROM `config` WHERE  name = 'site_location' AND value = 'stk');

    if @identifier is not null then

      INSERT INTO `config`
      (`name`, `value`, `title`, `description`, `field_id`, `options`, `type_id`, `editable`, `hidden`, `required`)
      VALUES
        ('eotc_enabled', 1, 'Enable OETC Module', 'Is EOTC module enabled?', 3, '', 11, 'false', 'false', 'false');

    else

      set @config := (SELECT configId FROM `config` WHERE  name = 'eotc_enabled');

      if @config is null then

        INSERT INTO `config`
          (`name`, `value`, `title`, `description`, `field_id`, `options`, `type_id`, `editable`, `hidden`, `required`)
        VALUES
          ('eotc_enabled', 0, 'Enable OETC Module', 'Is EOTC module enabled?', 3, '', 11, 'false', 'false', 'false');

      end if;

    end if;

  end;
//
delimiter ;
call check_config();
drop procedure check_config;
