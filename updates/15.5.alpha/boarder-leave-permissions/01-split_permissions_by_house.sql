-- Create a new permission for each house and existing permission combined
INSERT INTO userAccess (`uid`, `service`, `sid`, `type`)
  SELECT ua.uid, ua.service, rh.id, ua.type
  FROM luResidentialHouse rh
    CROSS JOIN userAccess ua
  WHERE ua.service = 6 AND ua.sid = 0;

-- Delete the old ones
DELETE FROM userAccess WHERE service = 6 AND sid = 0;
