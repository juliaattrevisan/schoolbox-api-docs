/* Updates all empty types caused by failed tarball update to '3rd Party Services' */
UPDATE `config` SET `type`='3rd Party Services' WHERE `type`='';
