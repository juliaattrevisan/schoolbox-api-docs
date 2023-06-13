# Delete any references to lu_service.name="Housemaster"
SET @serviceIdHousemaster = (SELECT id FROM lu_service WHERE name="Housemaster");
DELETE FROM userAccess WHERE service=@serviceIdHousemaster;

DELETE FROM lu_service WHERE name="Housemaster";

# Also maybe delete tables?
# luResidentialHouse

# Also maybe delete columns?
# user.residential_house_id

