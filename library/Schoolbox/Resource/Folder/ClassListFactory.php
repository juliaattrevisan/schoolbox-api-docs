<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Folder;

use Alaress\Database\Connector\IConnector;
use Alaress_Date_Format;

/**
 * Performs direct database access and returns arrays
 */
class ClassListFactory
{

    /** @var IConnector */
    private $database;


    /**
     * @param IConnector $database
     */
    public function __construct(IConnector $database)
    {
        $this->database = $database;
    }


    /**
     * Returns a set of course specs titles per specified folder that:
     * - are imported to that folder, and
     * - have assessments due between the specified dates.
     *
     * @param int[]                    $folderIds
     * @param Alaress_Date_Format      $start
     * @param Alaress_Date_Format|null $end
     * @return string[][] CourseSpec titles, indexed by folder.id, courseSpec.id
     */
    public function findFoldersWithDueImportedAssessments(
        array $folderIds,
        Alaress_Date_Format $start,
        ?Alaress_Date_Format $end
    ): array {
        if (!\count($folderIds)) {
            return [];
        }
        $courseSpecsPerFolder = array_fill_keys($folderIds, []);

        $pb = $this->database
            ->buildPredicateBuilder('')
            ->in('f.id', $folderIds)
            ->greaterThanOrEqualTo('a.due_date', $start->getDatabase());
        if ($end && !$end->isNull()) {
            $pb->lessThan('a.due_date', $end->getDatabase());
        }

        $whereClause = implode(' AND ', $pb->getWhere());

        $query = "
            SELECT f.id AS 'f.id', cs.id AS 'cs.id', csf.name as 'cs.title'
            FROM folder f
            JOIN course_implementation ci ON ci.folder_id = f.id
            JOIN course_specification cs ON ci.course_specification_id = cs.id
            JOIN folder csf ON csf.id = cs.folder_id
            JOIN course_unit_implementation cui ON ci.id = cui.course_implementation_id
            JOIN assessment_course_unit_implementation acui ON acui.course_unit_implementation_id = cui.id
            JOIN assessment a ON acui.assessment_id = a.id
            WHERE {$whereClause}
            GROUP BY f.id, ci.course_specification_id;
            ";

        $statement = $this->database->executeQuery($query, $pb->getParams());
        /** @noinspection PhpAssignmentInConditionInspection */
        while ($row = $statement->fetch()) {
            $courseSpecsPerFolder[$row['f.id']][$row['cs.id']] = $row['cs.title'];
        }
        return $courseSpecsPerFolder;
    }
}
