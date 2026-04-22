<?php

/**
 * IB System Purpose Mapper
 * @author    Haris Ali
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\IB\SystemPurpose;

use PDO;
use PDOStatement;
use Schoolbox\GenericCollection;
use Schoolbox\IB\Category\Mapper as CategoryMapper;
use Schoolbox\Mapper as BaseMapper;

/**
 * Class Mapper
 * @method Model createModel($idValue = null)
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method GenericCollection|Model[] findByColumnList($columnList, $options = [])
 *
 * @package Schoolbox\IB\SystemPurpose
 */
class Mapper extends BaseMapper
{
    /**
     * @inheritdoc
     */
    protected function mapping()
    {
        $this->hasTable('ib_system_purposes');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('category_id', CategoryMapper::class, 'id', null, null, 'category', 'category');
        $this->uses('name', self::TYPE_STRING);
    }

    /**
     * @inheritdoc
     */
    protected function getDefaultOrderByParams(): array
    {
        return [
            'name' => 'ASC',
        ];
    }

    /**
     * @inheritDoc
     */
    protected function getSelectString(): string
    {
        return "
            SELECT {$this->getColumnString()},
                   {$this->getJoinColumnString('category', 'category')}
        ";
    }

    /**
     * @inheritDoc
     */
    protected function getFromString(): string
    {
        return "
            FROM      {$this->getTable()}
            LEFT JOIN {$this->getJoinSql('category', '', 'category')}
        ";
    }

    /**
     * @param PDOStatement $statement
     * @return GenericCollection
     * @throws BaseMapper\DefinitionException
     * @throws BaseMapper\Exception
     */
    protected function hydrateSelect(PDOStatement $statement): GenericCollection
    {
        /** @var CategoryMapper $categoryMapper */
        $categoryMapper = $this->getJoinMapper('category');
        $systemPurposes = $this->createCollection();

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $systemPurpose = $this->hydrate($row);

            if ($systemPurpose) {
                $systemPurpose->setCategory($categoryMapper->hydrate($row, 'category'));
                $systemPurposes[$systemPurpose->getId()] = $systemPurpose;
            }
        }

        return $systemPurposes;
    }
}
