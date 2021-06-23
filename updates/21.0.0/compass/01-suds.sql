-- Compass is now an option
UPDATE `config`
SET `options` = 'csv;CSV Files:isams;iSAMS:kamar;Kamar:sentral;Sentral:tassapi;Tass API:compass;Compass'
WHERE (`name` = 'schooldata_import_data_source');
