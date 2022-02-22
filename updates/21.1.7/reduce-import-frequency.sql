UPDATE config SET value='twiceDaily' WHERE name like 'schooldata_import_frequency' AND value='hourly';
UPDATE config SET options='none;None:sb;Custom:twiceDaily;Twice Daily (6am and 6pm):daily;Daily (9pm)',description='Change how frequently data import occurs - custom, twice daily, or daily' WHERE name like 'schooldata_import_frequency';
