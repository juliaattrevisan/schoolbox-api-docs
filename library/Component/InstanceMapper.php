<?php

/**
 * 
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 * 
 */
 
class Component_InstanceMapper extends Mapper {
    protected function mapping () {
        $this->hasMap(array(
            'column'     => 'id',
            'property'   => 'id',
            'type'       => Mapper::TYPE_INTEGER,
            'identifier' => true
        ));

        $this->hasMap(array(
            'column'     => 'component_definition_id',
            'property'   => 'definitionId',
            'type'       => Mapper::TYPE_INTEGER,
        ));

        $this->hasMap(array(
            'column'     => 'module',
            'property'   => 'module',
            'type'       => Mapper::TYPE_INTEGER,
        ));

        $this->hasMap(array(
            'column'     => 'identifier',
            'property'   => 'identifier',
            'type'       => Mapper::TYPE_INTEGER,
        ));

        $this->hasMap(array(
            'column'     => 'position',
            'property'   => 'position',
            'type'       => Mapper::TYPE_INTEGER,
        ));

        $this->hasMap(array(
            'column'     => 'sequence',
            'property'   => 'sequence',
            'type'       => Mapper::TYPE_INTEGER,
        ));

        parent::mapping();
    }

    /**
     * @param String $module
     * @param Integer $identifier
     * @return Collection
     */
    public function findByContext ($module, $identifier) {
        $definitionMapper = Component_DefinitionMapper::getInstance();
        $configDefinitionMapper = Component_ConfigDefinitionMapper::getInstance();
        $configInstanceMapper = Component_ConfigMapper::getInstance();

        $statement = $this->database->prepare("
            SELECT {$this->getColumnString('ci')}
                 , {$definitionMapper->getColumnString('cd')}
                 , {$configDefinitionMapper->getColumnString('ccd')}
                 , {$configInstanceMapper->getColumnString('cci')}
            FROM component_instance ci
            INNER JOIN component_definition cd ON ci.component_definition_id = cd.id
              LEFT JOIN component_config_definition ccd ON cd.id = ccd.component_definition_id
              LEFT JOIN component_config_instance cci ON ccd.id = cci.component_config_definition_id AND cci.component_instance_id = ci.id
            WHERE ci.module = :module
            AND identifier = :identifier
            ORDER BY identifier ASC, `position` ASC, ci.sequence ASC
        ");

        $statement->bindParam(":module", $module);
        $statement->bindParam(":identifier", $identifier);

        $statement->execute();

        $rowset = $statement->fetchAll();

        $instanceCollection = $this->hydrateList($rowset, 'ci');
        $definitionCollection = $definitionMapper->hydrateList($rowset, 'cd');
        $configInstanceCollection = $configInstanceMapper->hydrateList($rowset, 'cci');
        $configDefinitionCollection = $configDefinitionMapper->hydrateList($rowset, 'ccd');

        $finalCollection = array();

        // map the definitions into their instances
        foreach($instanceCollection as $i => $instance) {

            // if this component has already been processed, just add the config and move on
            if(isset($finalCollection[$instance->id])) {
                $finalCollection[$instance->id]->config[$configDefinitionCollection[$i]->id] = $configDefinitionCollection[$i];
                continue;
            }

            // 1:1 :: instance:definition
            $instance->definition = $definitionCollection[$i];

            // n:1 :: configDefinitions:instance
            $instance->config[$configDefinitionCollection[$i]->id] = $configDefinitionCollection[$i];

            $finalCollection[$instance->id] = $instance;
        }

        // overwrite default configurations
        foreach($configInstanceCollection as $i => $confInstance) {
            if($confInstance->id != null) {
                $finalCollection[$confInstance->instanceId]->config[$confInstance->configDefinitionId]['value'] = $confInstance->value;
            }
        }

        return $finalCollection;
    }

    public function save ($object) {
        if ($object instanceof Component_Instance) {
            $dirtyInstanceCollection = new Collection();
            $dirtyInstanceCollection[] = $object;
        }
        else {
            $dirtyInstanceCollection = $object;
        }

        foreach ( $dirtyInstanceCollection as $dirtyInstance ) {

            $instanceMapper = Component_InstanceMapper::getInstance();

            $definitionMapper = Component_DefinitionMapper::getInstance();

            $dirtyInstance->definition = $definitionMapper->findById($dirtyInstance->definitionId);

            // Check for multiple instances with the same definition if disallowed
            if ( $dirtyInstance->definition->multiple == 'false' ) {
                $similarInstanceCollection = $instanceMapper->findByColumnList(array(
                    'module'     => 'dashboard',
                    'identifier' => $dirtyInstance->identifier,
                    'component_definition_id' => $dirtyInstance->definition->id
                ));

                foreach ( $similarInstanceCollection as $similarInstance ) {
                    if ( $similarInstance->id != $dirtyInstance->id ) {
                        return false;
                    }
                }
            }

            // Find original instance otherwise use blank instance for following conditional statements
            $cleanInstance = $instanceMapper->findById($dirtyInstance->id);

            if ( !$cleanInstance ) {
                $cleanInstance = new Component_Instance;
            }

            if ( $dirtyInstance->position != $cleanInstance->position || $dirtyInstance->sequence != $cleanInstance->sequence ) {

                // Resequence source components
                if ( !empty($cleanInstance->id) ) {
                    $sourceInstanceCollection = $instanceMapper->findByColumnList(array(
                        'module'     => 'dashboard',
                        'identifier' => $cleanInstance->identifier,
                        'position'   => $cleanInstance->position
                    ));

                    foreach ( clone $sourceInstanceCollection as $index => $sourceInstance ) {
                        if ( $sourceInstance->id == $cleanInstance->id ) {
                            unset($sourceInstanceCollection[$index]);
                        }
                        else if ( $sourceInstance->sequence > $cleanInstance->sequence ) {
                            $sourceInstanceCollection[$index]->sequence--;
                        }
                    }

                    parent::save($sourceInstanceCollection);
                }

                // Resequence destination components
                $destinationInstanceCollection = $instanceMapper->findByColumnList(array(
                    'module'     => 'dashboard',
                    'identifier' => $dirtyInstance->identifier,
                    'position'   => $dirtyInstance->position
                ));

                foreach ( $destinationInstanceCollection as $destinationInstance ) {
                    if ( $destinationInstance->sequence >= $dirtyInstance->sequence ) {
                        $destinationInstance->sequence++;
                    }
                }

                parent::save($destinationInstanceCollection);

            }
        }

        return parent::save($dirtyInstanceCollection, false);
    }

    public function delete ($object) {
        $instanceMapper = Component_InstanceMapper::getInstance();

        if ( is_a($object, 'Component_Instance') ) {
            $instanceCollection = new Collection;
            $instanceCollection[] = $object;
        }
        else {
            $instanceCollection = $object;
        }

        foreach ( $instanceCollection as $instance ) {
            $sourceInstanceCollection = $instanceMapper->findByColumnList(array(
                'module'     => 'dashboard',
                'identifier' => $instance->identifier,
                'position'   => $instance->position
            ));

            foreach ( clone $sourceInstanceCollection as $index => $sourceInstance ) {
                if ( $sourceInstance->id == $instance->id ) {
                    unset($sourceInstanceCollection[$index]);
                }
                else if ( $sourceInstance->sequence > $instance->sequence ) {
                    $sourceInstance->sequence--;
                }
            }

            parent::save($sourceInstanceCollection);
        }

        return parent::delete($instanceCollection);
    }
}