<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\Index;

use Schoolbox\Search\Index\Mapper;

/**
 * Helper class for persisting search index updates for a single subject.
 */
class Persistor
{
    /**
     * Constructor
     *
     * @param IBuilder $builder The search index builder: used for building search indices for the subject.
     * @param Mapper   $mapper  The search index mapper: used for persisting search indices.
     */
    public function __construct(private IBuilder $builder, private Mapper $mapper)
    {
    }

    /**
     * Persist changes to the search index for `$subject`.
     *
     * This persists updates to the index first, then deletions.
     *
     * @param object $subject The object to persist updates for.
     * @return void
     * @throws \Schoolbox\Mapper\Exception
     */
    public function persist(object $subject): void
    {
        $this->mapper->save(
            $this->mapper->createCollection(
                iterator_to_array($this->builder->buildUpdates(null, null, $subject))
            ),
        );
        $this->mapper->delete(
            $this->mapper->createCollection(
                iterator_to_array($this->builder->buildDeletes(null, null, $subject))
            ),
        );
    }
}
