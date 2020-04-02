-- Sentral is no longer an option in suds; we haven't built that connector yet
UPDATE `config` SET `options`='csv;CSV Files:isams;iSAMS:kamar;Kamar' WHERE `name`='schooldata_import_data_source';
