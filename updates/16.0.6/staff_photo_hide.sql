UPDATE `config` SET
  `title`='Teacher Photo Visibility',
  `description`='Select who can see the staff profile photos',
  `options`='0;Everyone:1;Hidden:2;Staff and Parents'
WHERE `name`='protect_teacher_photo';
