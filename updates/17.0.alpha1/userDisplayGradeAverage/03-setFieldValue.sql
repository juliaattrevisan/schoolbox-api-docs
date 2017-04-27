UPDATE `user`
  SET `display_grade_graph` = (SELECT `value` FROM `config` WHERE `name` = 'display_grade_graph');
