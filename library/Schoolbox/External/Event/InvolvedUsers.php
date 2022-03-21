<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Event;

/**
 * For a given event, find the final calculated list of users
 * which is the default users from sis_class, mutated by the
 * Overrides, then deduped.
 */
class InvolvedUsers
{

    /**
     * @var \Schoolbox\External\ClassEntryMapper
     */
    private \Schoolbox\External\ClassEntryMapper $classEntryMapper;


    /**
     * @var \Schoolbox\External\ClassInvolvementMapper
     */
    private \Schoolbox\External\ClassInvolvementMapper $classInvolvementMapper;


    /**
     * @var Overrides\Mapper
     */
    private \Schoolbox\External\Event\Overrides\Mapper $overrideMapper;


    /**
     * @var \Schoolbox\UserMapper
     */
    private \Schoolbox\UserMapper $userMapper;


    /**
     * @param \Schoolbox\External\ClassEntryMapper       $classEntryMapper
     * @param \Schoolbox\External\ClassInvolvementMapper $classInvolvementMapper
     * @param Overrides\Mapper                           $overrideMapper
     * @param \Schoolbox\UserMapper                      $userMapper
     */
    public function __construct(
        \Schoolbox\External\ClassEntryMapper $classEntryMapper,
        \Schoolbox\External\ClassInvolvementMapper $classInvolvementMapper,
        \Schoolbox\External\Event\Overrides\Mapper $overrideMapper,
        \Schoolbox\UserMapper $userMapper
    ) {
        $this->classEntryMapper = $classEntryMapper;
        $this->classInvolvementMapper = $classInvolvementMapper;
        $this->overrideMapper = $overrideMapper;
        $this->userMapper = $userMapper;
    }


    /**
     * Find the overall set of involved humans for $event
     *
     * @param Model $event
     * @return array
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \DebugBar\DebugBarException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     * @throws \Schoolbox\Mapper\Exception\NotFound
     */
    public function generate(\Schoolbox\External\Event\Model $event): array
    {

        /* Find the sis_class that corresponds to $event
         */
        $class = $this->classEntryMapper->findOneByColumn('code', $event->getCode());

        /* Find the usual guys in class 11
         */
        $involvements = $this->classInvolvementMapper->findByColumn('class_id', $class->getId());

        $usualUsers = [];

        foreach ($involvements as $involvement) {
            $usualUsers[$involvement->getExternalId()] = $this->userMapper->findByExternalId($involvement->getExternalId());
        }

        /* Remember a new set
         */
        $stack = $usualUsers;

        /* Find the overrides
         */
        $overrides = $this->overrideMapper->findByColumn('event_id', $event->getId());

        /* Decide how each $override affects the $stack set
         */
        foreach ($overrides as $override) {
            if (Overrides\Model::SUBTRACT === $override->getAction()) {
                unset($stack[$override->getUser()->getExternalId()]);
            } elseif (Overrides\Model::ADD === $override->getAction()) {
                $stack[$override->getUser()->getExternalId()] = $override->getUser();
            } elseif (Overrides\Model::CLEAR === $override->getAction()) {
                $stack = [];
            }
        }

        return $stack;
    }
}