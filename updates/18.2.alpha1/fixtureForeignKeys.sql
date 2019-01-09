DELETE u1 FROM sportsfixtures_team_participantuser u1
  JOIN sportsfixtures_team_participantuser u2
    ON u1.id < u2.id AND u1.match_id = u2.match_id AND u1.team_id = u2.team_id AND u1.user_id = u2.user_id;

ALTER TABLE `sportsfixtures_match`
  ADD INDEX `fk_sportsfixtures_match_away_school_idx` (`away_school_id` ASC),
  ADD INDEX `fk_sportsfixtures_match_home_school_idx` (`home_school_id` ASC),
  ADD INDEX `deleted_at_idx` (`deleted_at` ASC);

ALTER TABLE `sportsfixtures_match`
  ADD CONSTRAINT `fk_sportsfixtures_match_away_school`
    FOREIGN KEY (`away_school_id`)
      REFERENCES `sportsfixtures_school` (`id`)
      ON DELETE NO ACTION
      ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_sportsfixtures_match_home_school`
    FOREIGN KEY (`home_school_id`)
      REFERENCES `sportsfixtures_school` (`id`)
      ON DELETE NO ACTION
      ON UPDATE NO ACTION;

ALTER TABLE `sportsfixtures_team_participantuser`
  CHANGE COLUMN `match_id` `match_id` INT(11) NOT NULL,
  CHANGE COLUMN `team_id` `team_id` INT(11) NOT NULL,
  CHANGE COLUMN `user_id` `user_id` INT(11) UNSIGNED NOT NULL,
  ADD INDEX `fk_sportsfixtures_team_participantuser_match_idx` (`match_id` ASC),
  ADD INDEX `fk_sportsfixtures_team_participantuser_team_idx` (`team_id` ASC),
  ADD INDEX `fk_sportsfixtures_team_participantuser_user_idx` (`user_id` ASC),
  ADD UNIQUE INDEX `unique_player_idx` (`match_id` ASC, `team_id` ASC, `user_id` ASC),
  ADD INDEX `deleted_at_idx` (`deleted_at` ASC);


ALTER TABLE `sportsfixtures_team_participantuser`
  ADD CONSTRAINT `fk_sportsfixtures_team_participantuser_match`
    FOREIGN KEY (`match_id`)
      REFERENCES `sportsfixtures_match` (`id`)
      ON DELETE NO ACTION
      ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_sportsfixtures_team_participantuser_team`
    FOREIGN KEY (`team_id`)
      REFERENCES `sportsfixtures_team` (`id`)
      ON DELETE NO ACTION
      ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_sportsfixtures_team_participantuser_user`
    FOREIGN KEY (`user_id`)
      REFERENCES `user` (`id`)
      ON DELETE NO ACTION
      ON UPDATE NO ACTION;


ALTER TABLE `sportsfixtures_team_participantposition`
  CHANGE COLUMN `user_id` `user_id` INT(11) UNSIGNED NOT NULL ;

ALTER TABLE `sportsfixtures_team_participantposition`
  ADD INDEX `fk_sportsfixtures_team_participantposition_match_idx` (`match_id` ASC),
  ADD INDEX `fk_sportsfixtures_team_participantposition_user_idx` (`user_id` ASC),
  ADD INDEX `fk_sportsfixtures_team_participantposition_team_idx` (`team_id` ASC),
  ADD INDEX `deleted_at_idx` (`deleted_at` ASC);

ALTER TABLE `sportsfixtures_team_participantposition`
  ADD CONSTRAINT `fk_sportsfixtures_team_participantposition_match`
    FOREIGN KEY (`match_id`)
      REFERENCES `sportsfixtures_match` (`id`)
      ON DELETE NO ACTION
      ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_sportsfixtures_team_participantposition_team`
    FOREIGN KEY (`team_id`)
      REFERENCES `sportsfixtures_team` (`id`)
      ON DELETE NO ACTION
      ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_sportsfixtures_team_participantposition_user`
    FOREIGN KEY (`user_id`)
      REFERENCES `user` (`id`)
      ON DELETE NO ACTION
      ON UPDATE NO ACTION;


ALTER TABLE `sportsfixtures_team_participantcoach`
  CHANGE COLUMN `user_id` `user_id` INT(11) UNSIGNED NULL DEFAULT NULL ;

ALTER TABLE `sportsfixtures_team_participantcoach`
  ADD INDEX `fk_sportsfixtures_team_participantcoach_match_idx` (`match_id` ASC),
  ADD INDEX `fk_sportsfixtures_team_participantcoach_team_idx` (`team_id` ASC),
  ADD INDEX `fk_sportsfixtures_team_participantcoach_user_idx` (`user_id` ASC),
  ADD INDEX `deleted_at_idx` (`deleted_at` ASC);

ALTER TABLE `sportsfixtures_team_participantcoach`
  ADD CONSTRAINT `fk_sportsfixtures_team_participantcoach_match`
    FOREIGN KEY (`match_id`)
      REFERENCES `sportsfixtures_match` (`id`)
      ON DELETE NO ACTION
      ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_sportsfixtures_team_participantcoach_team`
    FOREIGN KEY (`team_id`)
      REFERENCES `sportsfixtures_team` (`id`)
      ON DELETE NO ACTION
      ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_sportsfixtures_team_participantcoach_user`
    FOREIGN KEY (`user_id`)
      REFERENCES `user` (`id`)
      ON DELETE NO ACTION
      ON UPDATE NO ACTION;


ALTER TABLE `sportsfixtures_team_user`
  CHANGE COLUMN `user_id` `user_id` INT(11) UNSIGNED NOT NULL ;

ALTER TABLE `sportsfixtures_team_user`
  ADD CONSTRAINT `fk_sportsfixtures_team_user_user`
    FOREIGN KEY (`user_id`)
      REFERENCES `user` (`id`)
      ON DELETE NO ACTION
      ON UPDATE NO ACTION;

ALTER TABLE `sportsfixtures_team_position`
  CHANGE COLUMN `user_id` `user_id` INT(11) UNSIGNED NOT NULL ;

ALTER TABLE `sportsfixtures_team_position`
  ADD CONSTRAINT `fk_sportsfixtures_team_position_user`
    FOREIGN KEY (`user_id`)
      REFERENCES `user` (`id`)
      ON DELETE NO ACTION
      ON UPDATE NO ACTION;

ALTER TABLE `sportsfixtures_team`
  ADD INDEX `deleted_at_idx` (`deleted_at` ASC);

ALTER TABLE `sportsfixtures_position`
  ADD INDEX `deleted_at_idx` (`deleted_at` ASC);

ALTER TABLE `sportsfixtures_match_itineraryitem`
  ADD INDEX `deleted_at_idx` (`deleted_at` ASC);


ALTER TABLE `sportsfixtures_result`
  ADD INDEX `fk_sportsfixtures_result_team_idx` (`team_id` ASC),
  ADD INDEX `deleted_at_idx` (`deleted_at` ASC);

ALTER TABLE `sportsfixtures_result`
  ADD CONSTRAINT `fk_sportsfixtures_result_team`
    FOREIGN KEY (`team_id`)
      REFERENCES `sportsfixtures_team` (`id`)
      ON DELETE NO ACTION
      ON UPDATE NO ACTION;

ALTER TABLE `sportsfixtures_coach`
  CHANGE COLUMN `user_id` `user_id` INT(11) UNSIGNED NULL DEFAULT NULL ;
ALTER TABLE `sportsfixtures_coach`
  ADD CONSTRAINT `fk_sportsfixtures_coach_user`
    FOREIGN KEY (`user_id`)
      REFERENCES `user` (`id`)
      ON DELETE NO ACTION
      ON UPDATE NO ACTION;

ALTER TABLE `sportsfixtures_division_user`
  CHANGE COLUMN `user_id` `user_id` INT(11) UNSIGNED NOT NULL ;

ALTER TABLE `sportsfixtures_division_user`
  ADD CONSTRAINT `fk_sportsfixtures_division_user_user`
    FOREIGN KEY (`user_id`)
      REFERENCES `user` (`id`)
      ON DELETE NO ACTION
      ON UPDATE NO ACTION;

ALTER TABLE `sportsfixtures_season`
  ADD INDEX `deleted_at_idx` (`deleted_at` ASC),
  ADD INDEX `archived_at_idx` (`archived_at` ASC);

ALTER TABLE `sportsfixtures_sport`
  ADD INDEX `deleted_at_idx` (`deleted_at` ASC);

ALTER TABLE `sportsfixtures_division`
  ADD INDEX `deleted_at_idx` (`deleted_at` ASC);

ALTER TABLE `sportsfixtures_venue`
  ADD INDEX `deleted_at_idx` (`deleted_at` ASC);

ALTER TABLE `sportsfixtures_school`
  ADD INDEX `deleted_at_idx` (`deleted_at` ASC);
