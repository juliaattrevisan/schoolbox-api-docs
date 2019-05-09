<?php
/**
 * Relations
 *
 * Imports user_relation records which only applies to Parents who have Students.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace Schoolbox\External\Connector\Isams\Base\Importer;

use Schoolbox\External\Normalise\User\Guardian;
use Schoolbox\External\UPS\Batchxml;
use Schoolbox\External\UserRelation;
use Schoolbox\External\UserRelationMapper;
use Schoolbox\Mapper;
use Schoolbox\Role;
use Schoolbox\UserMapper;

class Relations
{

    /**
     * @var Batchxml
     */
    private $api;


    /**
     * @var Usermapper
     */
    private $userMapper;


    /**
     * @var UserRelationMapper
     */
    private $userRelationMapper;


    /**
     * Profile constructor.
     *
     * @param Batchxml           $batchxml
     * @param UserMapper         $userMapper
     * @param UserRelationMapper $userRelationMapper
     */
    public function __construct(Batchxml $batchxml, UserMapper $userMapper, UserRelationMapper $userRelationMapper)
    {
        $this->api = $batchxml;
        $this->userMapper = $userMapper;
        $this->userRelationMapper = $userRelationMapper;
    }


    /**
     * Import user_relations
     */
    public function import(): void
    {
        $parents = $this->api->findAllParents();

        /* Delete all the records in user_relation
         * This is to ensure we always have the latest relation data in this table
         *
         * I did think to remove only records for each parent being examined, but that
         * seems like a lot of extra db calls for not a great deal of value in the end
         * So just wipe the slate clean at the start
         *
         * I also thought of putting this in the mapper but I kinda don't want people to
         * have access to such a powerful query - it's only used for a specific purpose
         */
        $this->userRelationMapper->getDatabase()->query("DELETE FROM {$this->userRelationMapper->getTable()}");

        /** @var Guardian $parent */
        foreach ($parents as $parent) {

            /* Find the parent's schoolbox account
             */
            try {
                $parentUser = $this->userMapper->findByExternalId($parent->getId(), Role::PARENT);
            } catch (Mapper\Exception $e) {

                /* noop: can't find schoolbox account
                 * therefore cannot build relation
                 */
                new \Alaress_Probe(sprintf(
                    'Could not create relations for parent %s %s (%s) - schoolbox account missing',
                    $parent->getGivenName(),
                    $parent->getLastname(),
                    $parent->getId()
                ));

                continue;
            }

            /* If the parents have students, build relations between them
             * Otherwise nothing to build
             */
            foreach ($parent->getStudents() as $student) {

                /* Find the student's schoolbox account
                 */
                try {
                    $studentUser = $this->userMapper->findByExternalId($student->getId(), Role::STUDENT);
                } catch (Mapper\Exception $e) {

                    /* noop: can't find schoolbox account
                     * therefore cannot build relation
                     */
                    new \Alaress_Probe(sprintf(
                        'Could not create relations for student %s %s (%s) - schoolbox account missing',
                        $student->getGivenName(),
                        $student->getLastname(),
                        $student->getId()
                    ));

                    continue;
                }

                /* Create relation - there's only one type atm
                 */
                /** @var UserRelation $relation */
                $relation = $this->userRelationMapper->createModel()
                    ->setUser($parentUser)
                    ->setRelation($studentUser)
                    ->setRelationType(UserRelation::RELATION_TYPE_PARENT);

                /* Save them into user_relation
                 */
                $this->userRelationMapper->save($relation);

                new \Alaress_Probe(sprintf(
                    'Creating relation between %s (%d) and %s (%d)',
                    $parentUser->getFullName(),
                    $parentUser->getId(),
                    $studentUser->getFullName(),
                    $studentUser->getId()
                ));
            }
        }
    }
}
