-- Data Source options, a freeform input field for now
UPDATE `config`
SET `options` = 'csv;CSV Files:isams;iSAMS:kamar;Kamar:sentral;Sentral:tassapi;Tass API'
WHERE `name` = 'schooldata_import_data_source';

