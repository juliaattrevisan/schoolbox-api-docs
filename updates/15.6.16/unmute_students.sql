DELETE msg_reroute_override
FROM msg_reroute_override
  INNER JOIN categoryCode ON CONCAT('service.1.', categoryCode.categoryID) = msg_reroute_override.msg_route_key
WHERE categoryCode.deleted_at IS NULL
      AND delivery = 'MU';