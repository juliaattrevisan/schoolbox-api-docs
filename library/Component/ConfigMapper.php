<?php

/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Permission\Access\UserAccess;

/**
 * @method Component_Config createModel($idValue = null)
 * @method Component_Config findOneByColumnList($columnList, array $options = [])
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

        // these callbacks would typically be asynchronous, however due to component
        // infrastructure limitations, they need to happen synchronously here

        $this->registerCallback(self::CALLBACK_POSTSAVE, function (Component_Config $model, Component_Config $previous = null): void {
            if (!$this->definitionIsSource($model)) {
                return;
            }
            $instance = $this->getInstanceFromConfig($model);
            if (!$instance->isHomepage()) {
                return;
            }
            if ($previous) {
                $diff = $this->diff($model, $previous);
                // if src value is not changed, do nothing.
                if (!isset($diff['value'])) {
                    return;
                }

                // update is a delete-and-replace action
                $this->handleDelete($instance, (int)$diff['value'][1]);
                $this->handleCreate($instance, (int)$diff['value'][0]);
            } else {
                $this->handleCreate($instance, (int)$model->getValue());
            }
        });

        $this->registerCallback(self::CALLBACK_POSTDELETE, function (Component_Config $model): void {
            if (!$this->definitionIsSource($model)) {
                return;
            }
            $instance = $this->getInstanceFromConfig($model);
            if (!$instance->isHomepage()) {
                return;
            }
            $this->handleDelete($instance, (int)$model->getValue());
        });
        $this->registerPersistSearchIndexCallbacks();
    }


    /**
     * Find the value of a particular component instance's config instance
     *
     * @param string $key
     * @param int    $instanceId
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
        } else {
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
    public function injectDefaultConfigCollection(int $instanceId, int $definitionId): void
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


    /**
     * @param Component_Instance $instance
     * @param int                $altSourceId
     */
    private function handleCreate(Component_Instance $instance, int $altSourceId)
    {
        $this->handle(
            'INSERT INTO msg_redirect (`from`, `to`) VALUES (:from, :to) ON DUPLICATE KEY UPDATE `to` = :to',
            $instance,
            $altSourceId
        );
    }

    /**
     * @param Component_Instance $instance
     * @param int                $altSourceId
     */
    private function handleDelete(Component_Instance $instance, int $altSourceId)
    {
        $this->handle(
            'DELETE FROM msg_redirect WHERE `from` = :from AND `to` = :to',
            $instance,
            $altSourceId
        );
    }

    /**
     * Run query
     * @param string             $query
     * @param Component_Instance $instance
     * @param int                $altSourceId
     */
    private function handle(string $query, Component_Instance $instance, int $altSourceId)
    {
        if (!$this->verifyWorkToBeDone($instance, $altSourceId)) {
            return;
        }

        foreach ([UserAccess::ACCESS_READ, UserAccess::ACCESS_WRITE, UserAccess::ACCESS_FULL] as $access) {
            $from = $this->determineSourcePath($instance, $access, $altSourceId);
            $to   = $this->determineDestinationPath($instance, $access);

            if (isset($from)) {
                $this->getDatabase()->query($query, ['from' => $from, 'to' => $to]);
            }
        }
    }

    /**
     * @param Component_Instance $instance
     * @param int                $altSourceId
     * @return bool
     */
    private function verifyWorkToBeDone(Component_Instance $instance, int $altSourceId)
    {
        $hostFolderId = $instance->getIdentifier();

        if (!$altSourceId || (int)$hostFolderId === (int)$altSourceId) {
            return false;
        }

        $ns = $instance->getDefinition()->getNamespace();

        if (!$ns) {
            return false;
        }

        return true;
    }

    /**
     * Determines what the source path should be for the message redirect.
     *
     * @param Component_Instance $instance
     * @param int                $access
     * @param int                $altSourceId
     * @return null|string
     */
    private function determineSourcePath(Component_Instance $instance, int $access, int $altSourceId): ?string
    {
        $namespace = $instance->getDefinition()->getNamespace();
        if (!$namespace) {
            return null;
        }
        $namespaceSlug = $namespace->getSlug();
        if ($namespaceSlug === null) {
            return null;
        }
        $namespaceSuffix = $instance->getDefinition()->getNamespaceSuffix();
        if ($namespaceSuffix === null) {
            return null;
        }
        $service = UserAccess::SERVICE_HOMEPAGE;
        return "*.notify.service.$service.$altSourceId.$access.$namespaceSlug.$namespaceSuffix";
    }

    /**
     * Determines what the destination path should be for the message redirect.
     *
     * @param Component_Instance $instance
     * @param int                $access
     * @return string
     */
    private function determineDestinationPath(Component_Instance $instance, int $access): string
    {
        $hostFolderId = $instance->getIdentifier();
        $service      = UserAccess::SERVICE_HOMEPAGE;
        return "*.notify.service.$service.$hostFolderId.$access.#";
    }

    /**
     * @param Component_Config $model
     * @return Component_Instance
     * @throws \Schoolbox\Mapper\Exception
     */
    private function getInstanceFromConfig(Component_Config $model): Component_Instance
    {
        $instance = \Component_InstanceMapper::getInstance()->findById($model->getInstanceId());

        // ensure definition hydrated
        $instance->setDefinition(\Component_DefinitionMapper::getInstance()->findById($instance->getDefinitionId()));

        return $instance;
    }

    /**
     * @param Component_Config $model
     * @return bool
     */
    private function definitionIsSource(Component_Config $model): bool
    {
        $definitions = Component_ConfigDefinitionMapper::getInstance()->findIdsByColumn('key', 'src');
        return in_array($model->getConfigDefinitionId(), $definitions, false);
    }
}
