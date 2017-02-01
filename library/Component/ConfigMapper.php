<?php

/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */

/**
 * Class Component_ConfigMapper
 * @method Component_Config createModel($idValue = null)
 */
class Component_ConfigMapper extends \Schoolbox\Mapper
{

    protected function mapping()
    {
        $this->hasTable('component_config_instance');
        $this->hasModel(Component_Config::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('component_config_definition_id', self::TYPE_INTEGER, null, 'configDefinitionId');
        $this->uses('component_instance_id', self::TYPE_INTEGER, null, 'instanceId');
        $this->uses('value', self::TYPE_STRING);
    }


    /**
     * This model does not have any notifications
     *
     * @param object $object
     * @param string $verb
     * @param null $previous
     * @return bool
     */
    protected function raiseNotification($object, $verb, $previous = null)
    {
        // noop
        return false;
    }

    /**
     * Find the value of a particular component instance's config instance
     *
     * @param $key
     * @param $instanceId
     * @return mixed
     */
    public function findValue($key, $instanceId)
    {
        $query = "
            SELECT {$this->getColumnString('cci')}
            FROM component_config_instance cci
            LEFT JOIN component_config_definition ON cci.`component_config_definition_id` = component_config_definition.`id`
            WHERE cci.component_instance_id = :instanceId
            AND `key` = :key
        ";

        $statement = $this->database->prepare($query);
        $statement->bindParam(':instanceId', $instanceId);
        $statement->bindParam(':key', $key);
        $statement->execute();

        $collection = new \Schoolbox\Collection();

        foreach ($statement as $row) {
            $cci = $this->hydrate($row, 'cci');

            $collection->add($cci);
        }

        if ($collection->count()) {
            return $collection->first()->getValue();
        } /* Otherwise spawn default values
         */
        else {
            $componentConfigDefinition = \Component_ConfigDefinitionMapper::getInstance()->findByColumn('key', $key);

            return $componentConfigDefinition->first()->getDefaultValue();
        }
    }
}
