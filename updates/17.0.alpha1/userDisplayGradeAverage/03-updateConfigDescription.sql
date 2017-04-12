UPDATE `config`
  SET
    `title`='Display Class Average on Student Grade Graph By Default',
    `description`='The class average is shown as a blue line on the students\' grade report. This option allows you to set whether that line defaults to displaying or not for new students. (This setting can be changed on a per-student basis.)'
WHERE `name` = 'display_grade_average';
