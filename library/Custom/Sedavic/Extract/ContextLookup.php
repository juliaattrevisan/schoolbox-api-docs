<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Custom\Sedavic\Extract;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Exception;
use Alaress\Database\Statement\Query\Base;

/**
 * Builds some basic data about courses and units for a given assessment
 */
class ContextLookup
{
    /**
     * For finding a row in mv_assessment_relation
     */
    private const QUERY_MV_RELATION = '
        SELECT 
            folder_id,
            course_implementation_id,
            course_unit_implementation_id,
            course_specification_id,
            course_unit_specification_id
        FROM v_assessment_relation
        WHERE assessment_id = :assessmentId
        LIMIT 1
    ';


    /**
     * For finding the (name, uuid) tuple of a course spec
     */
    private const QUERY_COURSE_SPEC = '
        SELECT f.name
        FROM course_specification cs
        LEFT JOIN folder f ON cs.folder_id = f.id
        WHERE cs.id = :id
    ';


    /**
     * For finding the (name, uuid) tuple of a course unit spec
     */
    private const QUERY_COURSE_UNIT_SPEC = '
        SELECT f.name 
        FROM course_unit_specification cus
        LEFT JOIN folder f ON cus.folder_id = f.id
        WHERE cus.id = :id
    ';


    /**
     * For finding the (name, uuid) tuple of a course spec
     * via a course impl
     */
    private const QUERY_COURSE_IMPL = '
        SELECT f.name 
        FROM course_implementation ci
        INNER JOIN course_specification cs ON ci.course_specification_id = cs.id
        INNER JOIN folder f ON cs.folder_id = f.id
        WHERE ci.id = :id
    ';


    /**
     * For finding the (name, uuid) tuple of a course unit spec
     * via a course unit impl
     */
    private const QUERY_COURSE_UNIT_IMPL = '
        SELECT f.name 
        FROM course_unit_implementation cui
        INNER JOIN course_unit_specification cus ON cui.course_unit_specification_id = cus.id
        LEFT JOIN folder f ON cus.folder_id = f.id
        WHERE cui.id = :id
    ';


    /* For prepared statements
     */
    private Base $mvRelationStatement;
    private Base $courseSpecStatement;
    private Base $courseUnitSpecStatement;
    private Base $courseImplStatement;
    private Base $courseUnitImplStatement;


    /**
     * Constructor.
     *
     * @param IConnector $db
     */
    public function __construct(
        private IConnector $db,
    ) {
        $this->mvRelationStatement = $this->db->prepareQuery(self::QUERY_MV_RELATION);
        $this->courseSpecStatement = $this->db->prepareQuery(self::QUERY_COURSE_SPEC);
        $this->courseUnitSpecStatement = $this->db->prepareQuery(self::QUERY_COURSE_UNIT_SPEC);
        $this->courseImplStatement = $this->db->prepareQuery(self::QUERY_COURSE_IMPL);
        $this->courseUnitImplStatement = $this->db->prepareQuery(self::QUERY_COURSE_UNIT_IMPL);
    }


    /**
     * Find the set of tuples that corresponds to the context of the $assessmentId
     * These tuples' data are fed into TagFactory to produce the actual tags themselves.
     *
     * @param int $assessmentId
     * @return array
     * @throws Exception
     * @throws \RuntimeException
     */
    public function find(int $assessmentId): array
    {
        $row = $this->findRow($assessmentId);

        /* Look up $assessmentId the row in mv_assessment_relation
         * Interpret the columns
         */

        /* If course_specification_id is set, find out its name and uuid
         * If course_unit_specification_id is set, find out its name and uuid
         */
        if ($row['course_specification_id'] && $row['course_unit_specification_id']) {
            $courseSpec = $this->findCourseSpec((int)$row['course_specification_id']);
            $courseUnitSpec = $this->findCourseUnitSpec((int)$row['course_unit_specification_id']);

            /* Figure out the course name and unit name
             */
            return [
                'course' => $courseSpec,
                'unit' => $courseUnitSpec
            ];
        }

        /* If course_implementation_id is set, find out its corresponding spec's name
         * If course_unit_implementation_id is set, find out its corresponding spec's name
         */
        if ($row['course_implementation_id'] && $row['course_unit_implementation_id']) {
            $courseSpec = $this->findCourseSpecViaImpl((int)$row['course_implementation_id']);
            $courseUnitSpec = $this->findCourseUnitSpecViaImpl((int)$row['course_unit_implementation_id']);

            /* Figure out the course name and unit name
             */
            return [
                'course' => $courseSpec,
                'unit' => $courseUnitSpec
            ];
        }

        throw new \RuntimeException(sprintf(
            'Assessment %s is not in a course',
            $assessmentId

        ));
    }


    /**
     * Finds a particular row from of mv_assessment_relation, for $assessmentId
     *
     * @param int $assessmentId
     * @return array
     * @throws Exception
     */
    private function findRow(int $assessmentId): array
    {
        $this->mvRelationStatement->execute([
            'assessmentId' => $assessmentId
        ]);

        return $this->mvRelationStatement->fetch(\PDO::FETCH_ASSOC);
    }


    /**
     * Finds the (name, uuid) tuple for a course spec
     *
     * @param int $courseSpecId
     * @return array
     * @throws Exception
     */
    private function findCourseSpec(int $courseSpecId): array
    {
        $this->courseSpecStatement->execute([
            'id' => $courseSpecId
        ]);

        return $this->courseSpecStatement->fetch(\PDO::FETCH_ASSOC);
    }


    /**
     * Finds the (name, uuid) tuple for a course unit spec
     *
     * @param int $courseUnitSpecId
     * @return array
     * @throws Exception
     */
    private function findCourseUnitSpec(int $courseUnitSpecId): array
    {
        $this->courseUnitSpecStatement->execute([
            'id' => $courseUnitSpecId
        ]);

        return $this->courseUnitSpecStatement->fetch(\PDO::FETCH_ASSOC);
    }


    /**
     * Finds the (name, uuid) spec tuple for a course impl
     *
     * @param int $courseImplId
     * @return array
     * @throws Exception
     */
    private function findCourseSpecViaImpl(int $courseImplId): array
    {
        $this->courseImplStatement->execute([
            'id' => $courseImplId
        ]);

        return $this->courseImplStatement->fetch(\PDO::FETCH_ASSOC);
    }


    /**
     * Finds the (name, uuid) spec tuple for a course unit impl
     *
     * @param int $courseUnitImplId
     * @return array
     * @throws Exception
     */
    private function findCourseUnitSpecViaImpl(int $courseUnitImplId): array
    {
        $this->courseUnitImplStatement->execute([
            'id' => $courseUnitImplId
        ]);

        return $this->courseUnitImplStatement->fetch(\PDO::FETCH_ASSOC);
    }
}