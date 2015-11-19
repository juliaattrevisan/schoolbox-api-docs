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
 * Class EffectMapper
 *
 * @package Schoolbox\Sync\Model
 * @method Effect findById($id)
 * @method Effect hydrate(array $row, $alias = '', $callback = '')
 */
class EffectMapper extends Mapper
{

    /**
     * Setup method. Implementing classes to define their SQL map here.
     *
     * @return void
     */
    protected function mapping()
    {
        $this->hasPrototype(new Effect());
        $this->hasTable('ups_effect');

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('user_property', self::TYPE_STRING);
        $this->uses('arguments', self::TYPE_JSON);

//        $this->joins('rule_id', RuleMapper::class);
        $this->joins('effect_type_id', EffectTypeMapper::class);
    }


    /**
     * @return string
     */
    protected function getSelectString()
    {
        return "
            SELECT {$this->getColumnString()},
                   {$this->getJoinColumnString('effect_type_id', 't')}
            FROM {$this->getTable()}
            LEFT JOIN {$this->getJoinSql('effect_type_id', '', 't')}
        ";
    }


    /**
     * @param PDOStatement $statement
     * @return Collection|Effect[]
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $collection = $this->createCollection();

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $effect = $this->hydrate($row);
            $effect->setEffectType($this->hydrateJoin($row, 'effect_type_id', 't'));

            $collection->add($effect);
        }

        return $collection;
    }


}