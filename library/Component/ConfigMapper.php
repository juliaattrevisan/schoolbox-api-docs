<?php

/**
 * 
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 * @
 */
 
class Component_ConfigMapper extends Mapper {

    protected $table = 'component_config_instance';

    protected function mapping () {
        $this->hasMap(array(
            'column'     => 'id',
            'property'   => 'id',
            'type'       => Mapper::TYPE_INTEGER,
            'identifier' => true
        ));

        $this->hasMap(array(
            'column'     => 'component_config_definition_id',
            'property'   => 'configDefinitionId',
            'type'       => Mapper::TYPE_INTEGER,
        ));

        $this->hasMap(array(
            'column'     => 'component_instance_id',
            'property'   => 'instanceId',
            'type'       => Mapper::TYPE_INTEGER,
        ));

        $this->hasMap(array(
            'column'     => 'value',
            'property'   => 'value',
            'type'       => Mapper::TYPE_STRING,
        ));

        parent::mapping();
    }

	/**
	 * TODO: PDO Array support for using WHERE IN
	 *
	 * http://stackoverflow.com/questions/14767530/php-using-pdo-with-in-clause-array
	 * http://stackoverflow.com/questions/920353/php-pdo-can-i-bind-an-array-to-an-in-condition
	 *
	 * @param $permittedValues
	 * @return Collection
	 */
	public function findIn($permittedValues) {
		$permittedValuesSql = implode(',', $permittedValues);

		$statement = $this->database->prepare("
            SELECT {$this->getColumnString()}
            FROM `{$this->table}`
            WHERE `component_instance_id` IN ({$permittedValuesSql})
        ");

		$statement->execute();

		$rowset = $statement->fetchAll();

		return $this->hydrateList($rowset);
	}
}