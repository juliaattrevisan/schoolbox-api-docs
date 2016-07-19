UPDATE `config` SET
  `title`='Teacher Photo Visibility',
  `description`='Select who can see the staff profile photos',
  `options`='0;Everyone:1;Staff Only:2;Staff and Parents',
  `field_id`='7'
WHERE `name`='protect_teacher_photo';
