<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Sync\Model;

use PDO;
use PDOStatement;
use Schoolbox\Collection;
use Schoolbox\Mapper;

/**
 * Class ConditionMapper
 *
 * @package Schoolbox\Sync\Model
 * @method Condition findById($id)
 * @method Condition hydrate(array $row, $alias = '', $callback = '')
 */
class ConditionMapper extends Mapper
{

    /**
     * Setup method. Implementing classes to define their SQL map here.
     *
     * @return void
     */
    protected function mapping()
    {
        $this->hasPrototype(new Condition());
        $this->hasTable('ups_condition');

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('arguments', self::TYPE_JSON);

//        $this->joins('rule_id', RuleMapper::class);
        $this->joins('condition_type_id', ConditionTypeMapper::class);

    }


    /**
     * @return string
     */
    protected function getSelectString()
    {
        return "
            SELECT {$this->getColumnString()},
                   {$this->getJoinColumnString('condition_type_id', 't')}
            FROM {$this->getTable()}
            LEFT JOIN {$this->getJoinSql('condition_type_id', '', 't')}
        ";
    }


    /**
     * @param PDOStatement $statement
     * @return Collection
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $collection = $this->createCollection();

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            try {
                $condition = $this->hydrate($row);
                $condition->setConditionType($this->hydrateJoin($row, 'condition_type_id', 't'));

                $collection->add($condition);
            } catch (\InvalidArgumentException $e) {
                // this will have been thrown if the arguments field failed to JSON-decode
                // not much we can really do about that except keep it from blowing everything else up...
            }
        }

        return $collection;
    }


}