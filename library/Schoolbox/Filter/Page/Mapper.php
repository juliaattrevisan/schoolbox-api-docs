<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Filter\Page;

use Alaress\Database\Predicate\InvalidColumnException;
use Schoolbox\Filter;
use Schoolbox\GenericCollection;

/**
 * @method Model createModel($idValue = null)
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method GenericCollection|Model[] createCollection($contents = [])
 * @method GenericCollection|Model[] findByColumn($columnName, $columnValue, $options = [])
 * @method GenericCollection|Model[] findByColumnList($columnList, $options = [])
 */
class Mapper extends \Schoolbox\Mapper
{

    /**
     * @inheritdoc
     */
    protected function mapping()
    {
        $this->hasTable('filter_page');
        $this->hasModel(Model::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('filter_id', Filter\Mapper::class, 'id', self::TYPE_MODEL, null, 'filter', 'filter');
        $this->uses('page', self::TYPE_STRING);
        $this->uses('enabled', self::TYPE_BOOLEAN);
        $this->uses('user_configurable', self::TYPE_BOOLEAN);

        $this->joinsReverseMulti('filter_page_id', Filter\Page\Option\Mapper::class, 'options', 'options');

        $this->registerCallback(self::CALLBACK_PREDELETE, [$this, 'hardDeleteOptions']);
    }


    /**
     * Pre hard-delete callback to hard-delete any Options for this pageFilter
     *
     * @param Model $model
     * @return bool
     * @throws \Schoolbox\Mapper\DefinitionException If join $name isn't on this mapper
     */
    public function hardDeleteOptions(Model $model)
    {
        /** @var Filter\Page\Option\Mapper $optionsMapper */
        $optionsMapper = $this->getJoinMapper('options');
        $options       = $optionsMapper->findByColumn('filter_page_id', $model->getId());
        $optionsMapper->delete($options);
        return true;
    }


    /**
     * Find all filters implemented on the specified page, along with their options and role-defaults
     *
     * * Indexed by pageFilter.id
     * * FilterPage options are indexed by option.key
     * * Option defaults are indexed by default.roleType
     *
     * @param string $page
     * @return Model[]|GenericCollection
     * @throws \Schoolbox\Mapper\DefinitionException If join $name isn't on this mapper
     * @throws \Schoolbox\Mapper\Exception           If an alias is incorrect
     * @throws \Schoolbox\Mapper\DefinitionException If join $name isn't on this mapper
     * @throws \Schoolbox\Mapper\Exception           If an alias is incorrect
     * @throws \UnexpectedValueException
     * @throws \PDOException
     * @throws InvalidColumnException
     * @throws \DomainException
     */
    public function findForPage($page)
    {
        $builder = $this->getPredicateBuilder();
        $builder->equals('p.page', $page);
        $whereClause = implode(' AND ', $builder->getWhere());

        /** @var Option\Mapper $optionMapper */
        $optionMapper = $this->getJoinMapper('options');
        /** @var Option\Defaults\Mapper $defaultsMapper */
        $defaultsMapper = $optionMapper->getJoinMapper('defaults');

        $query = "
            SELECT
                {$this->getColumnString('p')},
                {$optionMapper->getColumnString('o')},
                {$defaultsMapper->getColumnString('d')}
            FROM {$this->getTable()} p
            LEFT JOIN {$this->getJoinSql('options', 'p', 'o')}
            LEFT JOIN {$this->getJoinSql('options.defaults', 'o', 'd')}
            WHERE {$whereClause}
        ";

        $statement = $this->getDatabase()->prepare($query);
        $statement->execute($builder->getParams());

        $pageFilters = $this->createCollection();

        /** @noinspection PhpAssignmentInConditionInspection */
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $id = (int)$row["p.{$this->getIdentifierColumn()}"];
            if (!$pageFilters->offsetExists($id)) {
                $pageFilter = $this->hydrate($row, 'p')->setOptions($optionMapper->createCollection());
                $pageFilters->setOffset($id, $pageFilter);

                // Also add it to filter's page collection
                $pageFilter->getFilter()->getPages()->setOffset($page, $pageFilter);
            } else {
                $pageFilter = $pageFilters->getOffset($id);
            }

            $optionKey = $row['o.key'];
            $options   = $pageFilter->getOptions();
            if ($optionKey && !$options->offsetExists($optionKey)) {
                $options[$optionKey] = $optionMapper->hydrate($row, 'o')->setDefaults($defaultsMapper->createCollection());
                $options[$optionKey]->setFilterPage($pageFilter);
            }
            $option = $options[$optionKey];

            $defaultRoleType = $row['d.role_type'];
            if ($defaultRoleType && !$option->getDefaults()->offsetExists($defaultRoleType)) {
                $roleDefault                             = $defaultsMapper->hydrate($row, 'd');
                $option->getDefaults()[$defaultRoleType] = $roleDefault;
                $roleDefault->setFilterOption($option);
            }
        }

        return $pageFilters;
    }
}
