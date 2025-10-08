## fix xml_cache_timeout default value
update config set value = '1200' where name = 'xml_cache_timeout' and (value = '0' or value is null or value = '');
