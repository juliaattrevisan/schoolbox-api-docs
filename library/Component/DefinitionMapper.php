<?php

/**
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Alaress\Database\Predicate\Builder as PredicateBuilder;
use Schoolbox\Mapper\DefinitionException as MapperDefinitionException;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Mapper\Exception\Deleted as MapperExceptionDeleted;
use Schoolbox\Realtime\Message\NSMapper;

/**
 * Class Component_DefinitionMapper
 *
 * @method Component_Definition findOneByColumn($columnName, $columnValue, array $options = [])
 */
class Component_DefinitionMapper extends Schoolbox\Mapper
{
    /**
     * Define this mapper's mappings
     */
    protected function mapping()
    {
        $this->hasTable('component_definition');
        $this->hasModel(Component_Definition::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('name', self::TYPE_STRING);
        $this->uses('class', self::TYPE_STRING);
        $this->uses('description', self::TYPE_STRING);
        $this->uses('module', self::TYPE_STRING);
        $this->uses('config_copy', self::TYPE_STRING);
        $this->uses('enabled', self::TYPE_BOOLEAN);
        $this->uses('visible', self::TYPE_STRING);
        $this->uses('multiple', self::TYPE_STRING);
        $this->uses('component_category', self::TYPE_STRING);
        $this->joins('msg_namespace_slug', NSMapper::class, 'slug', null, null, 'namespace', 'namespace');
        $this->uses('msg_namespace_slug_suffix', self::TYPE_STRING, null, 'namespaceSuffix');
    }

    /**
     * {@inheritdoc}
     */
    public function hydrate(array $row, $alias = '', $callback = null)
    {
        // Search for an overriding definition class and use that to hydrate the model instead

        $classDefinitionOverride = str_replace('Controller', 'Definition', $row['cd.class']);

        if (
            class_exists($classDefinitionOverride) &&
            is_subclass_of($classDefinitionOverride, Component_Definition::class)
        ) {
            $this->hasModel($classDefinitionOverride);
        } else {
            $this->hasModel(Component_Definition::class);
        }

        // Hydrate row

        return parent::hydrate($row, $alias, $callback);
    }

    /**
     * Default to sorting by module, then name
     *
     * @param array $options
     * @return GenericCollection
     */
    public function findAll($options = [])
    {
        if (empty($options['sort'])) {
            $options['order'] = [
                'module' => 'ASC',
                'name'   => 'ASC'
            ];
        }

        return parent::findAll($options);
    }

    /**
     * Return the first part of the string required to select from this mapper.
     * Contains a join to a ridonkeykonk lookup table that defies ALL conventions
     *
     * @throws MapperDefinitionException
     * @return string
     */
    protected function getSelectString()
    {
        return "
            SELECT    {$this->getColumnString()},
                      `hcc`.`homepageComponentCategoryID` `hcc.id`,
                      `hcc`.`name` `hcc.name`,
                      {$this->getJoinColumnString('namespace', 'ns')},
                      `component_definition`.`class` as `cd.class`
            FROM      {$this->getTable()}
            LEFT JOIN `homepageComponentCategory` `hcc` ON
                      `component_definition`.`component_category` = `hcc`.`homepageComponentCategoryID`
            LEFT JOIN {$this->getJoinSql('namespace', '', 'ns')}
        ";
    }

    /**
     * @param PDOStatement $statement
     * @throws MapperException
     * @return GenericCollection|Component_Definition[]
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $c = $this->createCollection();

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $model = $this->hydrate($row);

            if ($row['hcc.id']) {
                // Handle the obscene lookup
                $model->setComponentCategory($row['hcc.id']);
                $model->setComponentCategoryName($row['hcc.name']);
            }

            if ($row['ns.slug']) {
                // If null, leave it as null since it's optional
                $model->setNamespace($this->hydrateJoin($row, 'namespace', 'ns'));
            }

            $c->add($model);
        }

        return $c;
    }

    /**
     * Builds a where part and params for this query out of a column list
     *
     * @param PredicateBuilder $predicate
     * @param array $columnList
     * @param array $options
     * @return array ['where' => the where parts of the query, 'params' => the parameters]
     * @throws UnderflowException
     */
    protected function buildWherePart(PredicateBuilder $predicate, $columnList, $options)
    {
        // Hide invisible components from all queries EXCEPT the ones that explicitly ask for it
        // it's like a soft-delete, but with an inverted boolenum, because SOMEONE thought that was ever a good idea...

        if (!(isset($options['hidden']) && $options['hidden'])) {
            $predicate->equals('visible', 'true');
        }

        // Only components that are enabled can be seen ever, this allows plans to turn off components
        $predicate->isTrue('enabled');

        return parent::buildWherePart($predicate, $columnList, $options);
    }

    /**
     * Find all component definitions including the hidden ones (uses in admin 'Components' list editor)
     *
     * @return Collection
     */
    public function findAllIncludeHidden()
    {
        return $this->findAll(['hidden' => true]);
    }

    /**
     * Gets all component definitions for this module.
     *
     * @param string $module
     * @param string $sortBy
     * @return Collection
     */
    public function findAllForModule($module, $sortBy = '')
    {
        return $this->findByColumn('module', $module, [
            'order' => [
                $sortBy => 'ASC',
                'name'  => 'ASC'
            ]
        ]);
    }

    /**
     * Create new custom components that need to be inserted AFTER the other new definitions
     *
     * Takes component structures in the following format
     * (yes i know it should be in a Definition Object.. but it doesnt have support for config)
     *
     * $classList = [
     *      'name'                  => 'Class List',
     *      'module'                => 'homepage',
     *      'component_category'    => '3',
     *      'class'                 => 'Component_Homepage_ClassListController',
     *      'description'           => 'Show your students portraits on your class homepage',
     *      'multiple'              => 'false',
     *      'config' => [
     *          'displayPortraits' => [
     *              'default_value'     => 'grid',
     *              'label'             => 'Layout Style'
     *          ]
     *      ]
     * ]
     *
     * @param array $component
     * @return int[]
     */
    public function create($component)
    {
        if (isset($component['config'])) {
            $config = $component['config'];
            unset($component['config']);
        }

        $pdoFields = [];

        foreach (array_keys($component) as $f) {
            $pdoFields[] = ':' . $f;
        }

        $pdoString = implode(', ', $pdoFields);
        $colString = implode('`, `', array_keys($component));

        $sql = "
            INSERT INTO   `component_definition` (`$colString`)
            VALUES        ($pdoString)
        ";

        if ($this->database->prepare($sql)->execute($component)) {
            if (isset($config) && is_array($config)) {
                $defnID = $this->database->lastInsertId();

                $stmt = $this->database->prepare("
					INSERT INTO `component_config_definition` (
					    `component_definition_id`,
					    `key`,
					    `default_value`,
					    `label`
                    ) VALUES (
                        :ccid,
                        :key,
                        :default_value,
                        :label
                    )
					ON DUPLICATE KEY UPDATE `default_value` = :default_value, `label` = :label
                ");

                foreach ($config as $key => $value) {
                    $stmt->execute([
                        'ccid'          => $defnID,
                        'key'           => $key,
                        'default_value' => $value['default_value'],
                        'label'         => $value['label']
                    ]);
                }

                return [(int)$defnID];
            }
        }

        return [];
    }

    /**
     * {@inheritdoc}
     *
     * @param  mixed $identifierValue
     * @return Component_Definition
     * @throws MapperException if no instance of model is found
     * @throws MapperExceptionDeleted if the model was found but has been deleted
     */
    public function findById($identifierValue)
    {
        return $this->findOneByColumn($this->getIdentifierColumn(), $identifierValue, ['hidden' => true]);
    }

    /**
     * {@inheritdoc}
     *
     * @param Component_Definition $model             The model to get values from
     * @throws MapperException if the map is not valid
     * @return array
     */
    public function dehydrate($model)
    {
        $dehydration = parent::dehydrate($model);

        // Override ns.id to ns.slug - this is to overcome a limitation of the Mapper system that can't handle the
        // scenario where you join onto a non-primary key

        $dehydration['msg_namespace_slug'] = $model->getNamespace() ? $model->getNamespace()->getSlug() : null;

        return $dehydration;
    }
}
