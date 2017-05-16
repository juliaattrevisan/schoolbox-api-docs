<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Pastoral\Revision;

use PDOStatement;
use Schoolbox\Collection;
use Schoolbox\Pastoral\Model as Pastoral;
use Schoolbox\Pastoral\Mapper as PastoralMapper;

class Mapper extends PastoralMapper
{
    /**
     * @inheritdoc
     */
    protected function mapping()
    {
        parent::mapping();

        // Override mappings from base pastoral class

        $this->hasTable('pastoral_revision');
        $this->hasModel(Model::class);

        // Don't want to auto-populate these in the revision, they need to be set from original pastoral record

        $this->uses('created_at', self::TYPE_DATETIME);
        $this->uses('updated_at', self::TYPE_DATETIME);

        // Add root pastoral record ID mapping

        $this->uses('root_id', self::TYPE_INTEGER);

        // We don't want the parents callback to be run

        $this->callback_queue = [];
    }

    /**
     * @inheritdoc
     */
    protected function getSelectString($revisions = false)
    {
        return
            "
            SELECT      {$this->getColumnString()},
                        {$this->getJoinColumnString('student', 's')},
                        {$this->getJoinColumnString('severity', 'sv')},
                        {$this->getJoinColumnString('owner', 'o')},
                        {$this->getJoinColumnString('type', 't')},
                        {$this->getJoinColumnString('category', 'c')}
            FROM        {$this->getTable()}
            LEFT JOIN   {$this->getJoinSql('student', '', 's')}
            LEFT JOIN   {$this->getJoinSql('severity', '', 'sv')}
            LEFT JOIN   {$this->getJoinSql('owner', '', 'o')}
            LEFT JOIN   {$this->getJoinSql('type', '', 't')}
            LEFT JOIN   {$this->getJoinSql('category', '', 'c')}
        ";
    }

    /**
     * Hydrate the record. This exists to prevent recursion when hydrating related records.
     *
     * @param PDOStatement $statement
     * @param bool $hydrateRelatedRecords
     * @return Collection
     */
    protected function hydrateSelectInternal(PDOStatement $statement, $hydrateRelatedRecords = true)
    {
        // Return pastoral records fully hydrated by default

        $collection = new Collection();

        foreach ($statement as $row) {

            // Flip the ID's for the purposes of reusing the base pastoral mappers hydrator

            $pastoral = $this->hydrateRow($row, $hydrateRelatedRecords);

            if ($pastoral instanceof Pastoral) {
                $collection->add($this->createRevision($pastoral));
            }
        }

        return $collection;
    }

    /**
     * Create a revision record from a pastoral record
     *
     * @param Pastoral $pastoral
     * @return Model
     */
    public function createRevision(Pastoral $pastoral) {

        // Super-laziness follows

        /** @var Model $revision */
        $revision = $this->createModel();

        $methods = get_class_methods(Pastoral::class);

        foreach ($methods as $method) {
            if (substr($method,0, 3) == 'set') {

                // We need to put the pastoral `id` into the revisions `root_id` field, everything else just get copied as-is

                if ($method != 'setId') {
                    $getter = $method;
                    $getter[0] = 'g';
                    if (is_callable([$pastoral, $getter]) && is_callable([$revision, $method])) {
                        $revision->$method($pastoral->$getter());
                    }
                } else {
                    $revision->setRootId($pastoral->getId());
                }
            }
        }

        return $revision;
    }
}