# DELETE cci FROM component_config_instance cci
# INNER JOIN component_config_definition ccd ON cci.component_config_definition_id = ccd.id
# INNER JOIN component_definition        cd  ON ccd.component_definition_id        = cd.id
# WHERE cd.class = 'Component_Homepage_CountdownController'
# AND ccd.`key` IN ('date', 'time');
#
# DELETE ccd FROM component_config_definition ccd
# INNER JOIN component_definition cd ON ccd.component_definition_id = cd.id
# WHERE cd.class = 'Component_Homepage_CountdownController'
# AND ccd.`key` IN ('date', 'time');
