UPDATE `config`
SET `description` = 'Insert the TASS communication rules type codes for all guardian relationships that can be associated with students. Use a comma separated list. (If blank, only TKCO relationships will be associated.)'
WHERE `name` = 'external_tass_commrules_typecode';