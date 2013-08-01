-- Fix correct grade value for 'Submitted' SB-2953
UPDATE `submission_errors` SET `normalise_as`='100' WHERE `code`='S';
