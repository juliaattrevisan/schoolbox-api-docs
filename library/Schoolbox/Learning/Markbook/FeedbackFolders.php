<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Markbook;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Predicate\Builder;
use Schoolbox\Collection;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception\NotFound;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\UserMapper;

/**
 * Finds a set of folder ids where the $students have a published feedback within a date range.
 */
class FeedbackFolders
{
    /**
     * @param IConnector $db
     * @param \External_Class $externalClass
     * @param UserMapper $userMapper
     */
    public function __construct(
        private IConnector $db,
        private \External_Class $externalClass,
        private \Schoolbox\UserMapper $userMapper,
    ) {
    }


    /**
     * For a given hydrated $folder, use its folder code to find a list of students from the sis.
     *
     * This list of students is pumped into findForStudents() to find all the places where they've got a published
     * feedback within the date range specified. This is more for your convenience than being a necessity.
     *
     * You can still use findForStudents() independently of this method, if you wanted.
     *
     * @param FolderModel $folder
     * @param \Alaress_Date_Format $from
     * @param \Alaress_Date_Format $to
     * @return array
     * @throws Exception
     * @throws NotFound
     */
    public function findForFolder(
        FolderModel $folder,
        \Alaress_Date_Format $from,
        \Alaress_Date_Format $to
    ): array {
        $studentIds = $this->findStudents($folder)->getPropertyValues('id');

        return $this->findForStudents($studentIds, $from, $to);
    }


    /**
     * For a given hydrated $folder, use its folder code to find a list of students from the sis.
     *
     * You might end up reusing this to reduce the Markbook's output via its studentIds reductive filter because
     * the swamp fetches and hydrates assessments; that sometimes includes more guys than are actually in your class
     * list. You don't need to show that.
     *
     * @param FolderModel $folder
     * @return Collection<\Schoolbox\User>
     * @throws Exception
     * @throws NotFound
     */
    public function findStudents(FolderModel $folder): Collection
    {
        /* Find a list of students currently involved in $folder, according to the sis
         */
        $students = $this->externalClass->getStudents($folder->getCodeString());
        $externalIds = array_column($students, 'synergy_id');

        /* Need their user ids, not the external ids
         *
         * TODO you could also put a Role::STUDENT as the second arg, but that would preclude any staff
         *  members being in their professional development workflow. Not sure how much that matters atm
         */
        return $this->userMapper->findByExternalIds($externalIds);
    }


    /**
     * Finds a set of folder ids where students had a published feedback between $from and $to (typically these will
     * correspond to the start of one academic period and the end of another: you get those separately outside of here)
     *
     * @param array $studentIds
     * @param \Alaress_Date_Format $from
     * @param \Alaress_Date_Format $to
     * @return null|array
     */
    public function findForStudents(
        array $studentIds,
        \Alaress_Date_Format $from,
        \Alaress_Date_Format $to
    ): ?array {
        /* Set up a predicate builder
         */
        $placeholders = Builder::getArrayPlaceholders($studentIds, 'studentIds');
        $params = Builder::getArrayParameters($studentIds, 'studentIds');

        /* Find all the assessments where $students had a published feedback in the date range specified
         */
        $statement = $this->db->prepareQuery("
            SELECT DISTINCT folder_id
            FROM v_most_recent_feedback vmrf
            INNER JOIN mv_assessment_relation mvar ON vmrf.assessment_id = mvar.assessment_id
            WHERE vmrf.student_id IN {$placeholders}
            AND vmrf.published_at BETWEEN :from AND :to
        ");

        $statement->execute(array_merge($params, [
            'from' => $from->startOfDay()->getDatabase(),
            'to' => $to->endOfDay()->getDatabase()
        ]));

        return $statement->fetchColumnArray('folder_id');
    }
}
