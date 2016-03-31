/**
 * Two separate finish dates:
 * * display_finish: the end of term as displayed and used in school
 * * actual_finish:  used for calculations only.
 *                   Whilst a term's display date is between two dates,
 *                   all dates between its start and the start of the next term
 *                   are considered part of that term.
 */
ALTER TABLE `term`
    CHANGE `finish` `display_finish` DATE NOT NULL,
    ADD COLUMN `actual_finish` DATE NULL AFTER `display_finish`
;
