### Update all previously imported codes and trims existing codes
UPDATE curriculum_node set code=trim(code) WHERE code REGEXP ' ';
