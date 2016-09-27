/*

The old scheme in Synergetic UPS rules is that for Students the "email.address" token
pulls in the students HOME address, whereas in staff/parents it pulls in the DEFAULT address.

In the new scheme "email.address" ALWAYS represents the DEFAULT address, and "homeEmail.address"
and "workEmail.address' represent their specific address types.

So in order to keep the existing behaviour and not screw up anybodies UPS we need to update
"email.address" in all student UPS effects to become "homeEmail.address" for Synergetic only

Hopefully that makes some kind of sense.

  _^^_
 (҂`_´)
 <,︻╦╤─ ҉ - - - - - -- U P S
  ,,\\,
 _/﹋\_

*/

UPDATE `ups_effect`
SET arguments = REPLACE(arguments, '{:email', '{:homeEmail')
WHERE `rule_id` = 1
AND (SELECT value FROM `config` WHERE name = 'external_type') = 'synergetic';