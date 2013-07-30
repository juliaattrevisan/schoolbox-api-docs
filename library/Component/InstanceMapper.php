<?php

/**
 * 
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 * 
 */

class Component_InstanceMapper extends Schoolbox\Mapper {
	protected function mapping() {
		$this->hasTable('component_instance');
		$this->hasModel('Component_Instance');
		$this->hasMap(array(
			'column'     => 'id',
			'property'   => 'id',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true
		));

		$this->hasMap(array(
			'column'     => 'component_definition_id',
			'property'   => 'definitionId',
			'type'       => self::TYPE_INTEGER,
		));

		$this->hasMap(array(
			'column'     => 'module',
			'property'   => 'module',
			'type'       => self::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'identifier',
			'property'   => 'identifier',
			'type'       => self::TYPE_INTEGER,
		));

		$this->hasMap(array(
			'column'     => 'position',
			'property'   => 'position',
			'type'       => self::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'sequence',
			'property'   => 'sequence',
			'type'       => self::TYPE_INTEGER,
		));
	}

	/**
	 * Find all the known instances of a particular kind of component within
	 * a module context
	 *
	 * @param $module
	 * @param $definitionId
	 * @return array
	 */
	public function findByDefinition($module, $definitionId) {
		$sql = '
			SELECT '.$this->getColumnString('ci').'
				 , '.Component_DefinitionMapper::getInstance()->getColumnString('cd').'
				 , '.Component_ConfigDefinitionMapper::getInstance()->getColumnString('ccd').'
				 , '.Component_ConfigMapper::getInstance()->getColumnString('cci')."
			FROM component_instance ci
			INNER JOIN component_definition cd ON ci.component_definition_id = cd.id
			LEFT JOIN component_config_definition ccd ON cd.id = ccd.component_definition_id
			LEFT JOIN component_config_instance cci ON ccd.id = cci.component_config_definition_id AND cci.component_instance_id = ci.id
			WHERE ci.module = :module
			AND ci.component_definition_id = :definitionId
			ORDER BY identifier ASC, `position` ASC, ci.sequence ASC
		";

		return $this->findByFields($sql, array(
			':module' => $module,
			':definitionId' => $definitionId
		));
	}

	/**
	 * Find component records by a module, but without an identifier
	 *
	 * @param $module
	 * @return array
	 */
	public function findByModule($module) {
		$sql = '
			SELECT '.$this->getColumnString('ci').'
				 , '.Component_DefinitionMapper::getInstance()->getColumnString('cd').'
				 , '.Component_ConfigDefinitionMapper::getInstance()->getColumnString('ccd').'
				 , '.Component_ConfigMapper::getInstance()->getColumnString('cci')."
			FROM component_instance ci
			INNER JOIN component_definition cd ON ci.component_definition_id = cd.id
			  LEFT JOIN component_config_definition ccd ON cd.id = ccd.component_definition_id
			  LEFT JOIN component_config_instance cci ON ccd.id = cci.component_config_definition_id AND cci.component_instance_id = ci.id
			WHERE ci.module = :module
			ORDER BY identifier ASC, `position` ASC, ci.sequence ASC
		";

		return $this->findByFields($sql, array(':module' => $module));
	}

	/**
	 * @param string $module
	 * @param string $identifier
	 *
	 * @return array
	 */
	public function findByContext($module, $identifier) {
		$sql = '
			SELECT '.$this->getColumnString('ci').'
				 , '.Component_DefinitionMapper::getInstance()->getColumnString('cd').'
				 , '.Component_ConfigDefinitionMapper::getInstance()->getColumnString('ccd').'
				 , '.Component_ConfigMapper::getInstance()->getColumnString('cci')."
			FROM component_instance ci
			INNER JOIN component_definition cd ON ci.component_definition_id = cd.id
			  LEFT JOIN component_config_definition ccd ON cd.id = ccd.component_definition_id
			  LEFT JOIN component_config_instance cci ON ccd.id = cci.component_config_definition_id AND cci.component_instance_id = ci.id
			WHERE ci.module = :module
			AND identifier = :identifier
			ORDER BY identifier ASC, `position` ASC, ci.sequence ASC
		";
		return $this->findByFields($sql, array(':module' => $module, ':identifier' => $identifier));
	}

	public function findByInstanceId($id) {
		$sql = '
			SELECT ' . $this->getColumnString('ci') . '
				 , ' . Component_DefinitionMapper::getInstance()->getColumnString('cd') .'
				 , ' . Component_ConfigDefinitionMapper::getInstance()->getColumnString('ccd') . '
				 , ' . Component_ConfigMapper::getInstance()->getColumnString('cci') . "
			FROM component_instance ci
			INNER JOIN component_definition cd ON ci.component_definition_id = cd.id
			  LEFT JOIN component_config_definition ccd ON cd.id = ccd.component_definition_id
			  LEFT JOIN component_config_instance cci ON ccd.id = cci.component_config_definition_id AND cci.component_instance_id = ci.id
			WHERE ci.id = :id
			ORDER BY identifier ASC, `position` ASC, ci.sequence ASC
		";

		$foo = $this->findByFields($sql, array(':id' => $id));

		return $foo;
	}

	/**
	 * @param $sql
	 * @param $fields
	 * @return array
	 */
	protected function findByFields($sql, $fields) {
		$statement = $this->database->prepare($sql);
		$statement->execute($fields);

		$rowset = $statement->fetchAll();

		//
		// Hydrate a whole bunch of stuff here.
		$instanceCollection         = $this->hydrateList($rowset, 'ci');
		$definitionCollection       = Component_DefinitionMapper::getInstance()->hydrateList($rowset, 'cd');
		$configInstanceCollection   = Component_ConfigMapper::getInstance()->hydrateList($rowset, 'cci');
		$configDefinitionCollection = Component_ConfigDefinitionMapper::getInstance()->hydrateList($rowset, 'ccd');

		$finalCollection = array();

		// map the definitions into their instances
		foreach($instanceCollection as $i => $instance) {
			/**
			 * @var $instance   Component_Instance
			 * @var $definition Component_Definition
			 * @var $config     Component_Config
			 * @var $config_def Component_ConfigDefinition
			 */
			$definition = $definitionCollection->getOffset($i);
			$config     = $configInstanceCollection->getOffset($i);
			$config_def = $configDefinitionCollection->getOffset($i);

			// if this component has already been processed, just add the config and move on
			$instance_id = $instance->getId();
			if (isset($finalCollection[$instance_id])) {
				/* @var $finalCollection[$instance_id] Component_Instance */
				$finalCollection[$instance_id]->getConfig()->setOffset($config_def->getId(), $config_def);
				continue;
			}

			// 1:1 :: instance:definition
			$instance->setDefinition($definition);

			// n:1 :: configDefinitions:instance
			$instance->getConfig()->setOffset($config_def->getId(), $config_def);

			$finalCollection[$instance_id] = $instance;
		}

		// overwrite default configurations
		foreach ($configInstanceCollection as $confInstance) {
			/* @var $confInstance Component_Config */
			if ($confInstance->getId() != null) {
				$finalCollection[$confInstance->getInstanceId()]->getConfig()
					->getOffset($confInstance->getConfigDefinitionId())
					->setValue($confInstance->getValue());
			}
		}

		return $finalCollection;
	}


	public function save ($object) {
		$dirtyInstanceCollection = $this->getModelAsCollection($object);

		foreach ($dirtyInstanceCollection as $dirtyInstance) {
			/* @var $dirtyInstance Component_Instance */

			$instanceMapper = Component_InstanceMapper::getInstance();

			$definitionMapper = Component_DefinitionMapper::getInstance();

			$dirtyInstance->setDefinition($definitionMapper->findById($dirtyInstance->getDefinitionId()));

			// Check for multiple instances with the same definition if disallowed
			if ($dirtyInstance->getDefinition()->getMultiple() == 'false') {
				$similarInstanceCollection = $instanceMapper->findByColumnList(array(
					'module'     => $dirtyInstance->getModule(),
					'identifier' => $dirtyInstance->getIdentifier(),
					'component_definition_id' => $dirtyInstance->getDefinition()->getId(),
				));

				foreach ($similarInstanceCollection as $similarInstance) {
					/* @var $similarInstance Component_Instance */
					if ($similarInstance->getId() != $dirtyInstance->getId()) {
						return false;
					}
				}
			}

			// Find original instance otherwise use blank instance for following conditional statements
			try {
				$cleanInstance = $instanceMapper->findById($dirtyInstance->getId());
			}
			catch (Schoolbox\Mapper\Exception $e) {
				$cleanInstance = new Component_Instance();
			}

			if ($dirtyInstance->getPosition() != $cleanInstance->getPosition()
				|| $dirtyInstance->getSequence() != $cleanInstance->getSequence()
			) {
				// Resequence source components
				$clean_instance_id = $cleanInstance->getId();
				if (!empty($clean_instance_id)) {
					$sourceInstanceCollection = $instanceMapper->findByColumnList(array(
						'module'     => $cleanInstance->getModule(),
						'identifier' => $cleanInstance->getIdentifier(),
						'position'   => $cleanInstance->getPosition(),
					));

					//
					// Iterate a copy of the source instance collection, because
					// it will be modified.
					foreach (clone $sourceInstanceCollection as $index => $sourceInstance) {
						/* @var $sourceInstance Component_Instance */
						if ($sourceInstance->getId() == $cleanInstance->getId()) {
							$sourceInstanceCollection->offsetUnset($index);
						}
						elseif ($sourceInstance->getSequence() > $cleanInstance->getSequence()) {
							$sourceInstance->setSequence($sourceInstance->getSequence()-1);
							$sourceInstanceCollection->offsetSet($index, $sourceInstance);
						}
					}

					parent::save($sourceInstanceCollection);
				}

				// Resequence destination components
				$destinationInstanceCollection = $instanceMapper->findByColumnList(array(
					'module'     => $dirtyInstance->getModule(),
					'identifier' => $dirtyInstance->getIdentifier(),
					'position'   => $dirtyInstance->getPosition(),
				));

				foreach ($destinationInstanceCollection as $destinationInstance) {
					/* @var $destinationInstance Component_Instance */
					if ($destinationInstance->getSequence() >= $dirtyInstance->getSequence()) {
						$destinationInstance->setSequence($destinationInstance->getSequence() + 1);
					}
				}

				parent::save($destinationInstanceCollection);

			}
		}

		return parent::save($dirtyInstanceCollection, false);
	}

	public function delete($object) {
		$instanceMapper = Component_InstanceMapper::getInstance();
		$instanceCollection = $this->getModelAsCollection($object);

		// container for parents if configs that need deletion
		$instanceIDs = array();

		foreach ($instanceCollection as $instance) {
			/* @var $instance Component_Instance */
			$instanceIDs[] = $this->database->quote($instance->getId(), PDO::PARAM_INT);
			$sourceInstanceCollection = $instanceMapper->findByColumnList(array(
				'module'     => 'dashboard',
				'identifier' => $instance->getIdentifier(),
				'position'   => $instance->getPosition()
			));

			//
			// Iterate a copy of the source instance collection, because it will
			// be modified.
			foreach (clone $sourceInstanceCollection as $index => $sourceInstance) {
				/* @var $sourceInstance Component_Instance */
				if ($sourceInstance->getId() == $instance->getId()) {
					$sourceInstanceCollection->offsetUnset($index);
				}
				elseif ($sourceInstance->getSequence() > $instance->getSequence()) {
					$sourceInstance->setSequence($sourceInstance->getSequence() - 1);
					$sourceInstanceCollection->offsetSet($index, $sourceInstance);
				}
			}

			parent::save($sourceInstanceCollection);
		}

		if (parent::delete($instanceCollection) && !empty($instanceIDs)) {
			// delete associated configs
			$deleteIDs = implode(',', $instanceIDs);
			$this->database->query("
				DELETE FROM component_config_instance
				WHERE component_instance_id IN ($deleteIDs);
			");

			return true;
		}
		return false;
	}

	/**
	 * Forcibly resequence a set of components, optionally protecting one
	 *
	 * @param $module
	 * @param $identifier
	 * @param int $protect - the component that has had its sequence changed, and so should be preserved
	 * @param string $position
	 * @return bool
	 */
	public function resequenceComponents($module, $identifier, $protect = 0, $position = '') {
		$cols = array('top', 'left', 'right');

		if($position) {
			if(!in_array($position, $cols)) {
				return false;
			}
			return $this->resequenceColumn($module, $identifier, $protect, $position);
		}
		else {
			foreach($cols as $c) {
				$success[] = $this->resequenceColumn($module, $identifier, $protect, $c);
			}
			return array_reduce($success, function($result, $item) {
				return $result || $item;
			});
		}
	}

	private function resequenceColumn($module, $identifier, $protect, $position) {
		// extract the current listing
		$stmt = $this->database->prepare("
			SELECT * FROM component_instance ci
			WHERE ci.module = :mod
			AND ci.identifier = :id
			AND ci.position = :pos
			ORDER BY ci.identifier ASC, ci.position ASC, ci.sequence ASC
		");

		$stmt->execute(array(
			'mod' => $module,
			'id' => $identifier,
			'pos' => $position
		));

		$cursequence = array();
		$resequence = array();

		while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			if($protect && $row['id'] == $protect) {
				$resequence[$row['sequence']] = $row['id'];
			}
			else {
				$cursequence[] = $row['id'];
			}
		}

		if(!count($cursequence)) {
			return true;
		}

		for($i = 0, $j = 1; $i < count($cursequence); ++$i, ++$j) {
			// protected has already been set
			if(isset($resequence[$j])) {
				// increment to bypass this sequence index it and keep going
				++$j;
			}
			$resequence[$j] = $cursequence[$i];
		}

		// put them in the right order
		ksort($resequence);

		$stmt = $this->database->prepare("
			UPDATE component_instance ci
			SET ci.`sequence` = :cis
			WHERE ci.id = :ciid
		");

		// apply the sequence changes
		foreach($resequence as $s => $id) {
			$stmt->execute(array(
				'ciid' => $id,
				'cis' => $s
			));
		}

		return true;
	}

	/**
	 * When a new component is inserted at sequence 0, increment all the indices
	 * @param $identifier
	 * @param string $position
	 * @param string $module
	 */
	public function cascadeSequence($identifier, $position = 'left', $module = 'homepage') {
		// cascade the sequence change
		$stmt = Database::getInstance()->prepare("
			UPDATE component_instance ci
			SET ci.`sequence` = ci.sequence + 1
			WHERE `position` = :pos
			AND ci.`module` = :mod
			AND ci.`identifier` = :id
		");
		$stmt->execute(array(
			'pos' => $position,
			'mod' => $module,
			'id' => $identifier
		));
	}
}
