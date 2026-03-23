-- Deprecating old curriculum packages out of the DB alongside package deletion from codebase --

DELETE FROM package WHERE location = 'AusGeneralCapabilities';
DELETE FROM package WHERE location = 'AusCrossCurricular';
DELETE FROM package WHERE location = 'VictorianCurriculum';
DELETE FROM package WHERE location = 'EarlyYearsFrameworkCurriculum';
DELETE FROM package WHERE location = 'NSWCurriculum';
DELETE FROM package WHERE location = 'AusGeneralCapabilitiesBadge';
