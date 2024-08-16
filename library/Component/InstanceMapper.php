<?php

/**
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Component\Eportfolio;
use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\Component\Homepage\Outline\Controller as HomepageOutlineController;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\Component\Homepage\Builder\Controller as CourseBuilderHomepageComponentController;
use Schoolbox\Mapper as BaseMapper;

/**
 * Class Component_InstanceMapper
 *
 * @method Component_Instance createModel($idValue = null)
 * @method Component_Instance findById($id)
 */
class Component_InstanceMapper extends BaseMapper
{
    /** @var Component_DefinitionMapper $componentDefinitionMapper */
    private $componentDefinitionMapper;

    /** @var Component_ConfigDefinitionMapper $componentConfigDefinitionMapper */
    private $componentConfigDefinitionMapper;

    /** @var Component_ConfigMapper $componentConfigMapper */
    private $componentConfigMapper;

    /**
     * Grab mappers used throughout this mess
     *
     * Component_InstanceMapper constructor.
     */
    public function __construct()
    {
        $this->componentDefinitionMapper       = Component_DefinitionMapper::getInstance();
        $this->componentConfigDefinitionMapper = Component_ConfigDefinitionMapper::getInstance();
        $this->componentConfigMapper           = Component_ConfigMapper::getInstance();

        parent::__construct();
    }

    /**
     * {@inheritDoc}
     */
    protected function mapping()
    {
        $this->hasTable('component_instance');
        $this->hasModel(Component_Instance::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('component_definition_id', self::TYPE_INTEGER, null, 'definitionId');
        $this->uses(
            'module',
            self::TYPE_STRING,
            self::COLUMN_ENUM,
            'module',
            [
                Component_Instance::MODULE_HOMEPAGE,
                Component_Instance::MODULE_HOMEPAGE_TEMPLATE,
                Component_Instance::MODULE_DASHBOARD,
                Component_Instance::MODULE_EPORTFOLIO,
                Component_Instance::MODULE_EPORTFOLIO_TEMPLATE,
            ]
        );
        $this->uses('identifier', self::TYPE_INTEGER);
        $this->uses('position', self::TYPE_STRING);
        $this->uses('sequence', self::TYPE_INTEGER);
    }

    /**'
     * {@inheritdoc}
     *
     * Prior to hydrating the row, check to see if there is a custom implementation of the component instance we
     * are hydrating, and if so create that model instead of the default Component_Instance one
     *
     * @param array $row
     * @param string $alias
     * @param null $callback
     */
    public function hydrate(array $row, $alias = '', $callback = null)
    {
        // Search for an overriding instance class and use that to hydrate the model instead
        $classInstanceOverride = null;
        if (isset($row['cd.class'])) {
            $classInstanceOverride = str_replace('Controller', 'Instance', $row['cd.class']);
        }

        if (
            isset($classInstanceOverride) &&
            class_exists($classInstanceOverride) &&
            is_subclass_of($classInstanceOverride, Component_Instance::class)
        ) {
            $this->hasModel($classInstanceOverride);
        } else {
            $this->hasModel(Component_Instance::class);
        }

        // Hydrate row
        $result = parent::hydrate($row, $alias, $callback);

        // Undo any override that was put in place.
        // Later on, I may try to hydrate again, and fail to add an object to a
        // collection because the model has change.
        $this->hasModel(Component_Instance::class);

        return $result;
    }

    /**
     * {@inheritDoc}
     *
     * @return string
     */
    public function getSelectString()
    {
        $componentInstanceTable           = $this->database->getQuoter()->quoteIdentifier($this->getTable());
        $componentDefinitionColumns       = $this->componentDefinitionMapper->getColumnString('cd');
        $componentConfigDefinitionColumns = $this->componentConfigDefinitionMapper->getColumnString('ccd');
        $componentConfigColumns           = $this->componentConfigMapper->getColumnString('cci');

        $sql = "
            SELECT      {$this->getColumnString()},
                        {$componentDefinitionColumns},
                        {$componentConfigDefinitionColumns},
                        {$componentConfigColumns}
            FROM        {$componentInstanceTable}
			INNER JOIN  `component_definition` `cd` ON
			            {$componentInstanceTable}.`component_definition_id` = `cd`.`id`
			LEFT JOIN   `component_config_definition` `ccd` ON
			            `cd`.`id` = `ccd`.`component_definition_id`
			LEFT JOIN   `component_config_instance` `cci` ON
			            `ccd`.`id` = `cci`.`component_config_definition_id` AND
			            `cci`.`component_instance_id` = {$componentInstanceTable}.`id`
        ";

        return $sql;
    }

    /**
     * Find all the known instances of a particular kind of component within a module context
     *
     * @param string $module
     * @param int $definitionId
     * @return Component_Instance[]|GenericCollection
     */
    public function findByDefinition($module, $definitionId)
    {
        $componentInstanceTable = $this->database->getQuoter()->quoteIdentifier($this->getTable());

        $sql = $this->getSelectString() . "
			WHERE       {$componentInstanceTable}.`module` = :module AND
			            {$componentInstanceTable}.`component_definition_id` = :definitionId
			ORDER BY    {$componentInstanceTable}.`identifier` ASC,
			            {$componentInstanceTable}.`position` ASC,
			            {$componentInstanceTable}.`sequence` ASC
		";

        return $this->findByFields($sql, [
            ':module'       => $module,
            ':definitionId' => $definitionId
        ]);
    }

    /**
     * Find component records by a module, but without an identifier
     *
     * @param string $module
     * @return Component_Instance[]|GenericCollection
     */
    public function findByModule($module)
    {
        $componentInstanceTable = $this->database->getQuoter()->quoteIdentifier($this->getTable());

        $sql = $this->getSelectString() . "
			WHERE       {$componentInstanceTable}.`module` = :module
			ORDER BY    {$componentInstanceTable}.`identifier` ASC,
			            {$componentInstanceTable}.`position` ASC,
			            {$componentInstanceTable}.`sequence` ASC
		";

        return $this->findByFields($sql, [
            ':module' => $module
        ]);
    }

    /**
     * @param string $module
     * @param string $identifier
     *
     * @return Component_Instance[]|GenericCollection
     */
    public function findByContext($module, $identifier)
    {
        $componentInstanceTable = $this->database->getQuoter()->quoteIdentifier($this->getTable());

        $sql = $this->getSelectString() . "
			WHERE       {$componentInstanceTable}.`module` = :module AND
                        {$componentInstanceTable}.`identifier` = :identifier AND
                        `cd`.`enabled` = 1
			ORDER BY    {$componentInstanceTable}.`identifier` ASC,
			            {$componentInstanceTable}.`position` ASC,
			            {$componentInstanceTable}.`sequence` ASC
		";

        return $this->findByFields($sql, [
            ':module'     => $module,
            ':identifier' => $identifier
        ]);
    }


    /**
     * Locates a Course Builder component on a homepage
     *
     * @param int $folderId
     * @throws MapperException
     * @return Component_Instance
     */
    public function findCourseBuilder($folderId)
    {
        $c = $this->findByContextAndClass(
            'homepage',
            $folderId,
            CourseBuilderHomepageComponentController::class
        );

        if ($c->count()) {
            return $c->first();
        }

        throw new MapperException("Instance of Course Builder not found on folder $folderId.");
    }

    /**
     * @param string $module
     * @param int $identifier
     * @param string $class
     * @return Component_Instance[]|GenericCollection
     */
    public function findByContextAndClass($module, $identifier, $class)
    {
        $componentInstanceTable = $this->database->getQuoter()->quoteIdentifier($this->getTable());

        $sql = $this->getSelectString() . "
            WHERE       {$componentInstanceTable}.`module` = :module AND
                        {$componentInstanceTable}.`identifier` = :identifier AND
                        `cd`.`enabled` = 1 AND
                        `cd`.`class` = :class
            ORDER BY    {$componentInstanceTable}.`identifier` ASC,
                        {$componentInstanceTable}.`position` ASC,
                        {$componentInstanceTable}.`sequence` ASC
        ";

        return $this->findByFields($sql, [
            ':module'     => $module,
            ':identifier' => $identifier,
            ':class'      => $class,
        ]);
    }

    /**
     * @param int $id
     * @param ?int $parent
     * @return Component_Instance|null
     */
    public function findByInstanceId($id, $parent = null)
    {
        $componentInstanceTable = $this->database->getQuoter()->quoteIdentifier($this->getTable());

        $params = [
            ':id' => $id
        ];

        $sql = $this->getSelectString() . "
			WHERE       {$componentInstanceTable}.`id` = :id
			";
        if (null !== $parent) {
            $sql .= "
            AND {$componentInstanceTable}.`identifier` = :parent
            ";
            $params[':parent'] = $parent;
        }
        $sql .= "
            ORDER BY    {$componentInstanceTable}.`identifier` ASC,
			            {$componentInstanceTable}.`position` ASC,
			            {$componentInstanceTable}.`sequence` ASC
		";

        return $this->findByFields($sql, $params)->first();
    }

    /**
     * @param string $sql
     * @param array $fields
     * @return Component_Instance[]|GenericCollection
     */
    protected function findByFields($sql, $fields)
    {
        $statement = $this->database->prepare($sql);

        $statement->execute($fields);

        // Hydrate a whole bunch of stuff here.

        $instanceCollection         = $this->createCollection();
        $definitionCollection       = $this->componentDefinitionMapper->createCollection();
        $configInstanceCollection   = $this->componentConfigMapper->createCollection();
        $configDefinitionCollection = $this->componentConfigDefinitionMapper->createCollection();

        // Hydrate rows
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $instanceId   = $row['id'];
            $definitionId = $row['cd.id'];
            $configId     = $row['cci.id'];
            $configDefId  = $row['ccd.id'];

            // The results will contain multiple rows referencing the same component, we only want to hydrate the
            // component definition once, so we need to test if it already exists in our collection
            /** @var Component_Definition|null $definition */
            $definition = $definitionCollection->getOffset($definitionId);

            if (empty($definition) && $definitionId) {
                // The definition had not been hydrated, hyrdate it and store it in our collection

                $definition = $this->componentDefinitionMapper->hydrate($row, 'cd');
                $definitionCollection->setOffset($definitionId, $definition);
            }

            // Hydrate Component Instance

            if ($instanceId && !$instanceCollection->offsetExists($instanceId)) {
                $instance = $this->hydrate($row);
                $instance->setDefinition($definition);
                $instanceCollection->setOffset($instanceId, $instance);
            }

            /** @var Component_Instance $instance */
            $instance = $instanceCollection->getOffset($instanceId);

            // Hydrate Component Instance Configuration

            if ($configDefId && !$configDefinitionCollection->offsetExists($configDefId)) {
                $configDefinition = $this->componentConfigDefinitionMapper->hydrate($row, 'ccd');
                $configDefinitionCollection->setOffset($configDefId, $configDefinition);
            } else {
                $configDefinition = $configDefinitionCollection->getOffset($configDefId);
            }

            if ($configId && !$configInstanceCollection->offsetExists($configId)) {
                $configInstanceCollection->setOffset($configId, $this->componentConfigMapper->hydrate($row, 'cci'));
            }

            /** @var Component_Config $configInstance */
            $configInstance = $configInstanceCollection->getOffset($configId);

            // Multiple Config Definitions In Each Instance

            if ($configDefinition) {
                $config = clone $configDefinition;

                if ($configInstance) {
                    $config->setValue($configInstance->getValue());
                }

                $instance->getConfig()->setOffset(
                    $config->getId(),
                    $config
                );
            }
        }

        return $instanceCollection;
    }

    /**
     * {@inheritDoc}
     *
     * @param mixed $object
     * @return bool|int[]
     */
    public function save($object)
    {
        $dirtyInstanceCollection = $this->getModelAsCollection($object);

        /* @var Component_Instance $dirtyInstance */
        foreach ($dirtyInstanceCollection as $dirtyInstance) {
            $dirtyInstance->setDefinition(
                $this->componentDefinitionMapper->findById($dirtyInstance->getDefinitionId())
            );

            // Check for multiple instances with the same definition if disallowed

            if ($dirtyInstance->getDefinition()->getMultiple() === false) {
                $similarInstanceCollection = $this->findByColumnList([
                    'module'                  => $dirtyInstance->getModule(),
                    'identifier'              => $dirtyInstance->getIdentifier(),
                    'component_definition_id' => $dirtyInstance->getDefinition()->getId(),
                ]);

                /* @var Component_Instance $similarInstance */
                foreach ($similarInstanceCollection as $similarInstance) {
                    if ($similarInstance->getId() != $dirtyInstance->getId()) {
                        return false;
                    }
                }
            }

            // Find original instance otherwise use blank instance for following conditional statements

            try {
                $cleanInstance = $this->findById($dirtyInstance->getId());
            } catch (MapperException $e) {
                $cleanInstance = new Component_Instance();
            }

            if (
                $dirtyInstance->getPosition() != $cleanInstance->getPosition() ||
                $dirtyInstance->getSequence() != $cleanInstance->getSequence()
            ) {
                // Resequence source components

                $clean_instance_id = $cleanInstance->getId();
                if (!empty($clean_instance_id)) {
                    $sourceInstanceCollection = $this->findByColumnList([
                        'module'     => $cleanInstance->getModule(),
                        'identifier' => $cleanInstance->getIdentifier(),
                        'position'   => $cleanInstance->getPosition(),
                    ]);

                    // Iterate a copy of the source instance collection, because it will be modified

                    /* @var Component_Instance $sourceInstance */
                    foreach (clone $sourceInstanceCollection as $index => $sourceInstance) {
                        if ($sourceInstance->getId() == $cleanInstance->getId()) {
                            $sourceInstanceCollection->offsetUnset($index);
                        } elseif ($sourceInstance->getSequence() > $cleanInstance->getSequence()) {
                            $sourceInstance->setSequence($sourceInstance->getSequence() - 1);
                            $sourceInstanceCollection->offsetSet($index, $sourceInstance);
                        }
                    }

                    parent::save($sourceInstanceCollection);
                }

                // Resequence destination components

                $destinationInstanceCollection = $this->findByColumnList([
                    'module'     => $dirtyInstance->getModule(),
                    'identifier' => $dirtyInstance->getIdentifier(),
                    'position'   => $dirtyInstance->getPosition(),
                ]);

                /* @var Component_Instance $destinationInstance */
                foreach ($destinationInstanceCollection as $destinationInstance) {
                    if ($destinationInstance->getSequence() >= $dirtyInstance->getSequence()) {
                        $destinationInstance->setSequence($destinationInstance->getSequence() + 1);
                    }
                }

                parent::save($destinationInstanceCollection);
            }
        }

        return parent::save($dirtyInstanceCollection);
    }

    /**
     * {@inheritDoc}
     *
     * @param object|Collection $object
     * @return bool
     */
    public function delete($object)
    {
        $instanceCollection = $this->getModelAsCollection($object);

        // Resequence components living in the same location as the deleted
        // component.
        // (Why was `module = 'dashboard'` in the query?)
        foreach ($instanceCollection as $instance) {
            /* @var $instance Component_Instance */
            $sourceInstanceCollection = $this->findByColumnList([
                'module'     => $instance->getModule(),
                'identifier' => $instance->getIdentifier(),
                'position'   => $instance->getPosition()
            ]);

            // Iterate a copy of the source instance collection, because it will be modified

            /* @var Component_Instance $sourceInstance */
            foreach (clone $sourceInstanceCollection as $index => $sourceInstance) {
                if ($sourceInstance->getId() == $instance->getId()) {
                    $sourceInstanceCollection->offsetUnset($index);
                } elseif ($sourceInstance->getSequence() > $instance->getSequence()) {
                    $sourceInstance->setSequence($sourceInstance->getSequence() - 1);
                    $sourceInstanceCollection->offsetSet($index, $sourceInstance);
                }
            }

            parent::save($sourceInstanceCollection);
        }

        // Delete everything related in a transaction.
        $this->getDatabase()->transact(function () use ($instanceCollection) {
            // Delete related eportfolio items:
            // these are a hard delete, so they must be deleted before deleting
            // the instance.
            $instanceIds = $instanceCollection->getPropertyValues('id');
            Eportfolio\AdvancedListMapper::getInstance()->deleteByColumn('component_instance_id', $instanceIds);
            Eportfolio\BasicListMapper::getInstance()->deleteByColumn('component_instance_id', $instanceIds);
            Eportfolio\LinkListMapper::getInstance()->deleteByColumn('component_instance_id', $instanceIds);
            Eportfolio\TextboxMapper::getInstance()->deleteByColumn('component_instance_id', $instanceIds);

            // Delete component config
            $this->componentConfigMapper->deleteByColumn('component_instance_id', $instanceIds);

            // Finally delete instances themselves
            parent::delete($instanceCollection);
        });
        return true;
    }

    /**
     * Forcibly resequence a set of components, optionally protecting one
     *
     * @param string $module
     * @param int $identifier
     * @param int $protect - the component that has had its sequence changed, and so should be preserved
     * @param string $position
     * @return bool
     */
    public function resequenceComponents($module, $identifier, $protect = 0, $position = '')
    {
        $cols = ['top', 'left', 'right'];

        if ($position) {
            if (!in_array($position, $cols)) {
                return false;
            }

            return $this->resequenceColumn($module, $identifier, $protect, $position);
        } else {
            $success = [];

            foreach ($cols as $c) {
                $success[] = $this->resequenceColumn($module, $identifier, $protect, $c);
            }

            return array_reduce($success, function ($result, $item) {
                return $result || $item;
            });
        }
    }

    /**
     * @param string $module
     * @param int $identifier
     * @param int $protect
     * @param int $position
     * @return bool
     */
    private function resequenceColumn($module, $identifier, $protect, $position)
    {
        $componentInstanceTable = $this->database->getQuoter()->quoteIdentifier($this->getTable());

        // Extract the current listing

        $stmt = $this->database->prepare("
			SELECT    *
			FROM      {$componentInstanceTable}
			WHERE     {$componentInstanceTable}.`module` = :mod
			AND       {$componentInstanceTable}.`identifier` = :id
			AND       {$componentInstanceTable}.`position` = :pos
			ORDER BY  {$componentInstanceTable}.`identifier` ASC,
			          {$componentInstanceTable}.`position` ASC,
			          {$componentInstanceTable}.`sequence` ASC
		");

        $stmt->execute([
            'mod' => $module,
            'id'  => $identifier,
            'pos' => $position
        ]);

        $cursequence = [];
        $resequence  = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($protect && $row['id'] == $protect) {
                $resequence[$row['sequence']] = $row['id'];
            } else {
                $cursequence[] = $row['id'];
            }
        }

        if (!count($cursequence)) {
            return true;
        }

        for ($i = 0, $j = 1; $i < count($cursequence); ++$i, ++$j) {
            // Protected has already been set
            if (isset($resequence[$j])) {
                // Increment to bypass this sequence index it and keep going
                ++$j;
            }
            $resequence[$j] = $cursequence[$i];
        }

        // Put them in the right order

        ksort($resequence);

        $stmt = $this->database->prepare("
			UPDATE  {$componentInstanceTable}
			SET     {$componentInstanceTable}.`sequence` = :cis
			WHERE   {$componentInstanceTable}.`id` = :ciid
		");

        // Apply the sequence changes
        foreach ($resequence as $s => $id) {
            $stmt->execute([
                'ciid' => $id,
                'cis'  => $s
            ]);
        }

        return true;
    }

    /**
     * When a new component is inserted at sequence 0, increment all the indices
     *
     * @param int $identifier
     * @param string $position
     * @param string $module
     */
    public function cascadeSequence($identifier, $position = 'left', $module = 'homepage')
    {
        $componentInstanceTable = $this->database->getQuoter()->quoteIdentifier($this->getTable());

        $stmt = $this->database->prepare("
			UPDATE    {$componentInstanceTable}
			SET       {$componentInstanceTable}.`sequence` = {$componentInstanceTable}.sequence + 1
			WHERE     {$componentInstanceTable}.`position` = :pos AND
			          {$componentInstanceTable}.`module` = :mod AND
			          {$componentInstanceTable}.`identifier` = :id
		");

        $stmt->execute([
            'pos' => $position,
            'mod' => $module,
            'id'  => $identifier
        ]);
    }

    /**
     * This method really only serves to retrieve components that have a 'src' config pointed at a particular category
     * so that we can delete them when that page is deleted. But we might have other uses for it later.
     *
     * @param string $key
     * @param mixed $value
     * @return Collection
     */
    public function findComponentByConfigValue($key, $value)
    {
        $stmt = $this->database->prepare("
			SELECT    `component_instance_id`
			FROM      `component_config_instance`
			WHERE     `value` = :value AND
			          `component_config_definition_id` IN
                      (
                          SELECT    `id`
                          FROM      `component_config_definition`
                          WHERE     `key` = :key
                      )
		");

        $stmt->execute([
            'key'   => $key,
            'value' => $value
        ]);

        $collection = new Collection();

        while ($record = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $collection->add(
                $this->findByInstanceId($record['component_instance_id'])
            );
        }

        return $collection;
    }

    /**
     * @param Folder $folder
     * @param array $config
     * @param string $controller
     * @return Component_Instance
     */
    public function injectNewComponent(
        Folder $folder,
        array $config = [],
        $controller = HomepageOutlineController::class
    ) {
        // Doesn't exist. We need that to exist. Make it exist.
        $definition = $this->componentDefinitionMapper->findOneByColumn('class', $controller);

        // Create new component
        $newInstance = $this->createModel()
            ->setModule('homepage')
            ->setIdentifier($folder->getId())
            ->setDefinitionId($definition->getId())
            ->setSequence(0)
            ->setPosition('top');

        // Save component
        $this->save($newInstance);

        // Apply config
        foreach ($config as $key => $value) {
            $configDefinitionIds = $this->componentConfigDefinitionMapper->findIdsByColumnList([
                'component_definition_id' => $definition->getId(),
                'key'                     => $key
            ]);

            $conf = $this->componentConfigMapper->createModel()
                ->setConfigDefinitionId($configDefinitionIds[0])
                ->setInstanceId($newInstance->getId())
                ->setValue($value);

            $newInstance->getConfig()->add($conf);
        }

        // Save the config
        $this->componentConfigMapper->save($newInstance->getConfig());

        // Reapply the sequencing so the homepage doesn't break
        $this->cascadeSequence($folder->getId(), 'top');

        return $newInstance;
    }

    /**
     * This model does not have any notifications
     *
     * @param object $object
     * @param string $verb
     * @param object|null $previous
     * @return bool
     */
    protected function raiseNotification($object, $verb, $previous = null)
    {
        return false;
    }
}
