-- SA-12178
-- migration required on the caption fields to remove leading and trailing whitespace

UPDATE `submission_grade`
    SET
        `submission_grade`.`caption` = TRIM(`submission_grade`.`caption`)
    WHERE
          CHAR_LENGTH(`submission_grade`.`caption`) <> CHAR_LENGTH(TRIM(`submission_grade`.`caption`));
