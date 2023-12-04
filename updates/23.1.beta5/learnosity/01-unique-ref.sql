# Ensure that learnosity_reference is unique
ALTER TABLE `assess_attempt`
    ADD UNIQUE INDEX `uq_assess_attempt_learnosity_reference` (`learnosity_reference` ASC);

