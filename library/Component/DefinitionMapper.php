<?php

/**
 * 
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 * @
 */

class Component_DefinitionMapper extends Schoolbox\Mapper {
	protected function mapping () {
		$this->hasTable('component_definition');
		$this->hasModel('Component_Definition');
		$this->hasMap(array(
			'column'     => 'id',
			'property'   => 'id',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true
		));

		$this->hasMap(array(
			'column'     => 'name',
			'property'   => 'name',
			'type'       => self::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'class',
			'property'   => 'class',
			'type'       => self::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'description',
			'property'   => 'description',
			'type'       => self::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'module',
			'property'   => 'module',
			'type'       => self::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'visible',
			'property'   => 'visible',
			'type'       => self::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'multiple',
			'property'   => 'multiple',
			'type'       => self::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'component_category',
			'property'   => 'componentCategory',
			'type'       => self::TYPE_STRING,
		));

	}

	public function findAll($options = array()) {
		$statement = $this->database->prepare("
			SELECT {$this->getColumnString()}
			FROM `{$this->table}` WHERE visible = 'true'
		");

		$statement->execute();

		$rowset = $statement->fetchAll();

		return $this->hydrateList($rowset);
	}

	/**
	 * Gets all component definitions for this module.
	 *
	 * @param string $module
	 * @param string $sortBy
	 *
	 * @return \Schoolbox\Collection
	 */
	public function findAllForModule($module, $sortBy = '') {
		$sort = '';
		if($sortBy) {
			foreach($this->map as $m)
			if($m['column'] == $sortBy) {
				$sort = "ORDER BY $sortBy ASC";
				break;
			}
		}
		$sql = "
			SELECT {$this->getColumnString()}
			FROM `{$this->table}`
			WHERE visible = 'true'
			AND module = :module
			$sort
		";

		$statement = $this->database->prepare($sql);

		$statement->bindParam('module', $module);
		$statement->execute();
		$rowset = $statement->fetchAll();
		$collection = $this->hydrateList($rowset);

		// bail at this point if we don't need to care about component category mappings
		if($module != 'homepage') {
			return $collection;
		}

		// collect the component categories and append the names

		$statement = $this->database->prepare("
			SELECT homepageComponentCategoryID `id`, `name`
			FROM homepageComponentCategory
		");
		$statement->execute();
		$rowset = $statement->fetchAll();

		$cats = array();
		foreach($rowset as $r) {
			$cats[$r['id']] = $r['name'];
		}

		foreach($collection as $definition) {
			/** @var Component_Definition $definition */
			if(isset($cats[$definition->getComponentCategory()])) {
				$definition->setComponentCategory($definition->getComponentCategory());
				$definition->setComponentCategoryName($cats[$definition->getComponentCategory()]);
			}
		}

		return $collection;
	}


	/**
	 * Create new custom components that need to be inserted AFTER the other new definitions
	 *
	 * Takes component structures in the following format (yes i know it should be in a Definition Object.. but it doesnt have support for config)
	 *
	 * $classList = array(
				'name'  => 'Class List',
				'module' => 'homepage',
				'component_category' => '3',
				'class' => 'Component_Homepage_ClassListController',
				'description' => 'Show your students portraits on your class homepage',
				'multiple' => 'false',
				'config' => array(
					'displayPortraits' => array(
						'default_value' => 'grid',
						'label' => 'Layout Style'
					),
				)
			);
	 *
	 */
	public function create($component) {


		if (isset($component['config'])) {
			$config = $component['config'];
			unset($component['config']);
		}

		$pdoFields = array();
		foreach (array_keys($component) as $f) {
			$pdoFields[] = ':' . $f;
		}
		$pdoString = implode(', ', $pdoFields);
		$colString = implode('`, `', array_keys($component));

		$sql = "INSERT INTO `component_definition` (`$colString`) VALUES ($pdoString)";

		if ($this->database->prepare($sql)->execute($component)) {
			if (is_array($config)) {
				$defnID = $this->database->lastInsertId();
				$sql = "
					INSERT INTO `component_config_definition`
					  (`component_definition_id`, `key`, `default_value`, `label`)
					VALUES
					  (:ccid, :key, :default_value, :label)
					ON DUPLICATE KEY UPDATE `default_value` = :default_value, `label` = :label";
				$stmt = $this->database->prepare($sql);


				foreach ($config as $key => $c) {
					$stmt->execute(
						array(
							'ccid' => $defnID,
							'key' => $key,
							'default_value' => $c['default_value'],
							'label' => $c['label']
						)
					);
				}
			}
		}
	}
}