/* Insert error messages
*/
drop procedure if exists check_error;
delimiter //
create procedure check_error()
  begin

    set @e1 := (SELECT id FROM `error` WHERE  id = 160);
    set @e2 := (SELECT id FROM `error` WHERE  id = 161);
    set @e3 := (SELECT id FROM `error` WHERE  id = 163);
    set @e4 := (SELECT id FROM `error` WHERE  id = 165);

    if @e1 is null then
      INSERT INTO `error` (`id`, `message`) VALUES ('160', 'This event could not be found.');
    end if;

    if @e2 is null then
      INSERT INTO `error` (`id`, `message`) VALUES ('161', 'Please upload a RAMS file.');
    end if;

    if @e3 is null then
      INSERT INTO `error` (`id`, `message`) VALUES ('163', 'Unknown event status.');
    end if;

    if @e4 is null then
      INSERT INTO `error` (`id`, `message`) VALUES ('165', 'An EOTC event may not be approved if there are no student attendees.');
    end if;

  end;
//
delimiter ;
call check_error();
drop procedure check_error;
