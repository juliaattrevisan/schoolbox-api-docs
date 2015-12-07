<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Sync\Rule;


use PDOStatement;
use Schoolbox\Collection;
use Schoolbox;
use Schoolbox\Sync;

/**
 * Class RuleMapper
 *
 * @package Schoolbox\Sync\Model
 * @method Model findById($id)
 * @method Model createModel()
 * @method Collection|Model[] findByColumn($columnName, $columnValue, $options = array())
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
        $this->hasTable('ups_rule');

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('role_type', self::TYPE_STRING);
        $this->uses('enabled', self::TYPE_BOOLEAN);
        $this->uses('sequence', self::TYPE_INTEGER);
        $this->uses('name', self::TYPE_STRING);
        $this->uses('description', self::TYPE_STRING);

        $this->hasJoin('ups_condition', [
            'mapper'    => Sync\Condition\Mapper::class,
            'this_key'  => 'id',
            'other_key' => 'rule_id',
            'multiple'  => true
        ]);

        $this->hasJoin('ups_effect', [
            'mapper'    => Sync\Effect\Mapper::class,
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
        /** @var Model[] $collection */
        $collection = parent::hydrateSelect($statement);

        // ideally this would have been all captured in the initial query, but hydrating a cross-product isn't straightforward...
        foreach ($collection as $rule) {
            $rule->setConditions($this->getJoinMapper('ups_condition')->findByColumn('rule_id', $rule->getId()));
            $rule->setEffects($this->getJoinMapper('ups_effect')->findByColumn('rule_id', $rule->getId()));
        }

        return $collection;
    }


    /**
     * @param string $roleType
     * @return int
     */
    public function findNextAvailableSequence($roleType)
    {
        return $this->database->query("
          SELECT COALESCE(MAX(sequence), 0) + 1
          FROM ups_rule
          WHERE role_type = ?
        ", [ $roleType ])->fetchColumn();
    }

    /**
     * Resequence Rules
     *
     * @param $sequencing
     * @return bool
     */
    public function updateRuleSequence($sequencing)
    {
        $identifierName = $this->identifier['column'];

        $statement = $this->database->prepare("
            UPDATE `{$this->table}`
            SET sequence = :sequence
            WHERE `{$identifierName}` = :identifierValue
        ");

        for ($i = 1; $i <= count($sequencing); $i++) {
            $statement->bindValue(':sequence', $i);
            $statement->bindValue(':identifierValue', $sequencing[$i-1]);

            $statement->execute();
        }

        return true;
    }
}