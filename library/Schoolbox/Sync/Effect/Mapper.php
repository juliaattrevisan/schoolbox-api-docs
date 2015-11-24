<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Sync\Effect;


use PDO;
use PDOStatement;
use Schoolbox\Collection;
use Schoolbox;

/**
 * Class EffectMapper
 *
 * @package Schoolbox\Sync\Model
 * @method Model findById($id)
 * @method Model hydrate(array $row, $alias = '', $callback = '')
 */
class Mapper extends Schoolbox\Mapper
{

    /**
     * Setup method. Implementing classes to define their SQL map here.
     *
     * @return void
     */
    protected function mapping()
    {
        $this->hasPrototype(new Model());
        $this->hasTable('ups_effect');

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('user_property', self::TYPE_STRING);
        $this->uses('arguments', self::TYPE_JSON);

//        $this->joins('rule_id', RuleMapper::class);
        $this->joins('effect_type_id', Type\Mapper::class);
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
     * @return Collection|Model[]
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $collection = $this->createCollection();

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            try {
                $effect = $this->hydrate($row);
                $effect->setEffectType($this->hydrateJoin($row, 'effect_type_id', 't'));

                $collection->add($effect);
            } catch (\InvalidArgumentException $e) {
                // this will have been thrown if the arguments field failed to JSON-decode
                // not much we can really do about that except keep it from blowing everything else up...
            }
        }

        return $collection;
    }


}