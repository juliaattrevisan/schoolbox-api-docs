<?php

/**
 * ManyManyRelation
 *
 * Many-many relation table, where $relationTableDefinition is a
 * pseudo-mapping of column names
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace EOTC;

class ManyManyRelation extends \Schoolbox\Mapper
{

    /**
     * An array definition
     *
     * @var array
     */
    private $relationTableDefinition = array();

    /**
     * An array definition
     *
     * @var array
     */
    private $join = array();

    /**
     * Constructor
     *
     * @param array $relationTableDefinition
     * @param array $join
     */
    public function setup($relationTableDefinition = array(), $join = array())
    {
        $this->setRelationTableDefinition($relationTableDefinition);
        $this->setJoin($join);
    }

    /**
     * Implemented out of necessity
     */
    protected function mapping()
    {
        // noop
    }

    /**
     * Find results of join
     *
     * @return mixed
     */
    public function find()
    {
        $join = $this->getJoin();
        $relationTableDefinition = $this->getRelationTableDefinition();

        $query = "
			SELECT {$join['relation']['column']}
			FROM `{$relationTableDefinition['name']}`
			WHERE {$join['column']} = :value
		";

        $statement = $this->database->prepare($query);
        $statement->bindParam(":value", $join['value']);
        $statement->execute();

        /* Find all the primary keys in the relation table and objectify
         * them into a collection
         */
        $ids = array();

        foreach ($statement as $row) {
            $ids[] = $row[$join['relation']['column']];
        }

        /** @var \Schoolbox\Mapper $mapper */
        $mapper = $join['relation']['mapper'];

        $allInstances = $mapper->findByColumn($join['relation']['key'], $ids, isset($join['relation']['options']) ? $join['relation']['options'] : array());

        return $allInstances;
    }

    /* Getters/Setters
     */

    /**
     * Set join
     *
     * @param array $join
     */
    private function setJoin($join)
    {
        $this->join = $join;
    }

    /**
     * Get join
     *
     * @return array
     */
    public function getJoin()
    {
        return $this->join;
    }

    /**
     * Set relation table definition
     *
     * @param array $relationTableDefinition
     */
    private function setRelationTableDefinition($relationTableDefinition)
    {
        $this->relationTableDefinition = $relationTableDefinition;
    }

    /**
     * Get relation table definition
     *
     * @return array
     */
    private function getRelationTableDefinition()
    {
        return $this->relationTableDefinition;
    }
}
