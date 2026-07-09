-- Add Edlink as an option
UPDATE `config`
SET `options` = 'csv;CSV Files:isams;iSAMS:kamar;Kamar:sentral;Sentral:tassapi;Tass API:compass;Compass:wonde;Wonde:liss;LISS:veracross;Veracross:civica;Civica:edlink;Edlink'
WHERE (`name` = 'schooldata_import_data_source');
