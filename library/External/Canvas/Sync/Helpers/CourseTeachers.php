<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace External\Canvas\Sync\Helpers;

use Alaress\Database;
use Alaress\Database\Connector\IConnector;
use Alaress\Database\Predicate\Builder;
use External\Canvas\Importer\CanvasDb;
use Schoolbox\Role;
use UnderflowException;

use function array_keys;
use function count;
use function implode;

/**
 * Responsible for consistently determining which users in Schoolbox teach a Canvas course
 */
class CourseTeachers
{
    public function __construct(private IConnector $schoolboxDb, private CanvasDb $canvasDb)
    {
    }


    /**
     * Returns a map of Canvas Course.canvas_id => Schoolbox user.id
     *
     * @param string[] $courseCanvasIds
     * @return int[]
     * @throws Database\Exception
     * @throws Database\Predicate\InvalidColumnException
     * @throws UnderflowException
     */
    public function getCourseTeacherMap(array $courseCanvasIds): array
    {
        if (!count($courseCanvasIds)) {
            return [];
        }

        $externalIdToUserIdMap = $this->getSchoolboxStaffExternalIds();
        if (count($externalIdToUserIdMap)) {
            return [];
        }

        $pb          = (new Builder(new Database\Quoter\Mysql\Quoter(), ''))
            ->in('course.canvas_id', array_keys($courseCanvasIds))
            ->equals('enrollment.type', 'TeacherEnrollment')
            ->in('pseudonym.sis_user_id', array_keys($externalIdToUserIdMap));
        $whereClause = implode(' AND ', $pb->getWhere());
        $sql         = "
                SELECT
                    course.id             'course.canvas_id',
                    pseudonym.sis_user_id 'pseudonym.sis_user_id'
                FROM courses course
                JOIN enrollments enrollment ON enrollment.course_id = course.id
                JOIN pseudonyms pseudonym ON pseudonym.user_id = enrollment.user_id
                WHERE {$whereClause}
                GROUP BY course.id
            ";
        if (!$this->canvasDb->isCanvasData2) {
            $sql         = "
                SELECT
                    course.canvas_id      'course.canvas_id',
                    pseudonym.sis_user_id 'pseudonym.sis_user_id'
                FROM course_dim course
                JOIN enrollment_dim enrollment ON enrollment.course_id = course.id
                JOIN pseudonym_dim pseudonym ON pseudonym.user_id = enrollment.user_id
                WHERE {$whereClause}
                GROUP BY course.canvas_id
            ";
        }
        $statement   = $this->canvasDb->getConnector()->executeQuery($sql, $pb->getParams());

        $courseTeachers = [];
        while ($row = $statement->fetch()) {
            $courseCanvasId                  = (int)$row['course.canvas_id'];
            $courseTeacherSisId              = $row['pseudonym.sis_user_id'];
            // @phpstan-ignore-next-line: I don't know how this can be considered to always be null
            $courseTeacherUserId             = $externalIdToUserIdMap[$courseTeacherSisId] ?? null;
            $courseTeachers[$courseCanvasId] = (int)$courseTeacherUserId;
        }
        return $courseTeachers;
    }


    /**
     * Return all user.ids of schoolbox users who are staff and have an externalId, indexed by user.synergyId
     *
     * @return int[]
     * @throws Database\Exception
     */
    private function getSchoolboxStaffExternalIds(): array
    {
        $sql = "
            SELECT u.id, u.synergy_id
            FROM user u
            JOIN `groups` g ON g.id = u.group_id
            WHERE
                  g.deleted_at IS NULL AND
                  u.deleted_at IS NULL AND
                  g.group_type = :roleType AND
                  u.synergy_id IS NOT NULL
        ";

        $externalIds = [];
        $statement   = $this->schoolboxDb->executeQuery($sql, ['roleType' => Role::STAFF]);
        while ($row = $statement->fetch()) {
            $externalIds[$row['synergy_id']] = $row['id'];
        }
        return $externalIds;
    }
}
