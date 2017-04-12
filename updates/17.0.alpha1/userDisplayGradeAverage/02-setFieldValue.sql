UPDATE `user`
  SET `display_grade_average` = (SELECT `value` FROM `config` WHERE `name` = 'display_grade_average');
