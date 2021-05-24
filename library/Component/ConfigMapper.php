<?php

/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

/**
 * @method Component_Config createModel($idValue = null)
 * @method Component_Config findOneByColumnList($columnList, array $options = [])
 */
class Component_ConfigMapper extends \Schoolbox\Mapper
{
    /** @var Component_ConfigNotifier */
    private $moduleNotifierObject;


    /**
     * Default setup for any other objects which compose the mapper.
     */
    protected function postSetup()
    {
        $this->moduleNotifierObject = new Component_ConfigNotifier($this->getConfigLoader());
    }


    protected function mapping()
    {
        $this->hasTable('component_config_instance');
        $this->hasModel(Component_Config::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('component_config_definition_id', self::TYPE_INTEGER, null, 'configDefinitionId');
        $this->uses('component_instance_id', self::TYPE_INTEGER, null, 'instanceId');
        $this->uses('value', self::TYPE_STRING);

        // these callbacks would typically be asynchronous, however due to component
        // infrastructure limitations, they need to happen synchronously here

        $this->registerCallback(self::CALLBACK_POSTSAVE, function (Component_Config $model, Component_Config $previous = null) {
            if ($previous) {
                $this->moduleNotifierObject->notifySystemUpdated($model, $this->getSessionUser(), $this->diff($model, $previous));
            } else {
                $this->moduleNotifierObject->notifySystemCreated($model, $this->getSessionUser());
            }
        });

        $this->registerCallback(self::CALLBACK_POSTDELETE, function (Component_Config $model) {
            $this->moduleNotifierObject->notifySystemDeleted($model, $this->getSessionUser());
        });
    }


    /**
     * Due to the synchronous "notification" handling above, there is no need to raise notifications the normal way
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
     * @param string $key
     * @param int $instanceId
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

    /**
     * Injects default component config instance
     * @param int $instanceId
     * @param int $definitionId
     * @throws \Schoolbox\Mapper\Exception
     */
    function injectDefaultConfigCollection(int $instanceId, int $definitionId): void
    {
        $collection                 = $this->createCollection();
        $componentConfigDefinitions = \Component_ConfigDefinitionMapper::getInstance()->findByColumn(
            'component_definition_id',
            $definitionId
        );

        // Apply config
        /** @var Component_ConfigDefinition $componentConfigDefinition */
        foreach ($componentConfigDefinitions as $componentConfigDefinition) {
            $conf = $this->createModel()
                         ->setConfigDefinitionId($componentConfigDefinition->getId())
                         ->setInstanceId($instanceId)
                         ->setValue($componentConfigDefinition->getDefaultValue());
            $collection->add($conf);
        }

        // Save the config
        $this->save($collection);
    }
}
