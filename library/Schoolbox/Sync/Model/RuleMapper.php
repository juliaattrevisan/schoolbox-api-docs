<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Sync\Model;


use PDOStatement;
use Schoolbox\Collection;
use Schoolbox\Mapper;

/**
 * Class RuleMapper
 *
 * @package Schoolbox\Sync\Model
 * @method Rule findById($id)
 * @method Collection|Rule[] findByColumn($columnName, $columnValue, $options = array())
 */
class RuleMapper extends Mapper
{

    /**
     * Setup method. Implementing classes to define their SQL map here.
     *
     * @return void
     */
    protected function mapping()
    {
        $this->hasPrototype(new Rule());
        $this->hasTable('ups_rule');

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('role_type', self::TYPE_STRING);
        $this->uses('enabled', self::TYPE_BOOLEAN);
        $this->uses('sequence', self::TYPE_INTEGER);

        $this->hasJoin('ups_condition', [
            'mapper'    => ConditionMapper::class,
            'this_key'  => 'id',
            'other_key' => 'rule_id',
            'multiple'  => true
        ]);

        $this->hasJoin('ups_effect', [
            'mapper'    => EffectMapper::class,
            'this_key'  => 'id',
            'other_key' => 'rule_id',
            'multiple'  => true
        ]);

    }


    /**
     * @param PDOStatement $statement
     * @return Collection
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        /** @var Rule[] $collection */
        $collection = parent::hydrateSelect($statement);

        // ideally this would have been all captured in the initial query, but hydrating a cross-product isn't straightforward...
        foreach ($collection as $rule) {
            $rule->setConditions($this->getJoinMapper('ups_condition')->findByColumn('rule_id', $rule->getId()));
            $rule->setEffects($this->getJoinMapper('ups_effect')->findByColumn('rule_id', $rule->getId()));
        }

        return $collection;
    }

}