<?php
/**
 * GridGenerator
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\Rubric;

use Alaress\Database\Predicate\Builder;
use Schoolbox\User;


class GridGenerator
{
    /**
     * @var RubricMapper
     */
    private $rubricMapper;

    /**
     * GridGenerator constructor.
     *
     * @param RubricMapper $rubricMapper
     */
    public function __construct(RubricMapper $rubricMapper)
    {
        $this->rubricMapper = $rubricMapper;
    }

    /**
     * @param Rubric $grid
     * @param int[]  $selectedDescriptorIds
     *
     * @return Rubric
     */
    public function applyMark(Rubric $grid, array $selectedDescriptorIds)
    {
        foreach ($grid->getAllDescriptors() as $descriptor) {
            $selected = \in_array($descriptor->getId(), $selectedDescriptorIds, false);
            $descriptor->setSelected($selected);
        }

        return $grid;
    }


    /**
     * Using a Predicate Builder and a bool, find the pieces of the Rubric
     *
     * @param Rubric  $grid
     * @param bool    $publishedOnly
     * @param Builder $pb
     * @return Rubric
     */
    private function applyStudentMarkQuery(Rubric $grid, bool $publishedOnly, Builder $pb): Rubric
    {
        $whereClause = implode(' AND ', $pb->getWhere());
        $dateOrder   = $publishedOnly ? 'sr.published_at' : 'sr.created_at';

        // retrieve marks (descriptor selections for student)
        $query = "
            SELECT rm.descriptor_id
            FROM rubric_mark rm
            LEFT JOIN submission_return sr ON sr.id = rm.return_id
            WHERE rm.valid = TRUE
            AND rm.return_id = (
              SELECT sr.id
              FROM submission_return sr
              WHERE $whereClause
              ORDER BY $dateOrder DESC
              LIMIT 1
            )
        ";

        $statement = $this->rubricMapper->getDatabase()->query($query, $pb->getParams());

        $descriptorIds = [];

        while ($id = $statement->fetchColumn()) {
            $descriptorIds[$id] = true;
        }

        return $this->applyMark($grid, array_keys($descriptorIds));
    }


    /**
     * Alias to applyStudentMark() for a particular IResponse instance
     *
     * @param Rubric $grid
     * @param User   $student
     * @param bool   $publishedOnly Whether to exclude draft marks
     * @param int    $responseId
     *
     * @return Rubric
     */
    public function applyStudentMarkForResponse(Rubric $grid, User $student, bool $publishedOnly, int $responseId)
    {
        $pb = new Builder($this->rubricMapper->getDatabase(), '');
        $pb->equals('sr.owner', $student->getId());
        $pb->equals('sr.box_id', $grid->getSubmissionBox()->getId());
        $pb->isNull('sr.deleted_at');
        $pb->equals('sr.student_submission_id', $responseId);

        if ($publishedOnly) {
            $pb->notNull('sr.published_at');
        }

        return $this->applyStudentMarkQuery($grid, $publishedOnly, $pb);
    }


    /**
     * @param Rubric   $grid
     * @param User     $student
     * @param bool     $publishedOnly Whether to exclude draft marks
     *
     * @return Rubric
     */
    public function applyStudentMark(Rubric $grid, User $student, bool $publishedOnly)
    {
        $pb = new Builder($this->rubricMapper->getDatabase(), '');
        $pb->equals('sr.owner', $student->getId());
        $pb->equals('sr.box_id', $grid->getSubmissionBox()->getId());
        $pb->isNull('sr.deleted_at');

        if ($publishedOnly) {
            $pb->notNull('sr.published_at');
        }

        return $this->applyStudentMarkQuery($grid, $publishedOnly, $pb);
    }

}