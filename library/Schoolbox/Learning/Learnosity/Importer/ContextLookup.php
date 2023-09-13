<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Importer;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Exception;
use Schoolbox\Learning\Learnosity\TagFactory;

/**
 * Builds some basic data about courses and homepages for the
 * express purpose of fulfilling the TagFactory args.
 *
 * These tuples are installed on first use so that you keep a
 * minimum set in memory at a given point in time
 *     (course spec uuid, course spec name)
 *     (unit spec uuid, unit spec name)
 *     (folder code, folder name)
 */
class ContextLookup
{
    /**
     * A set of (encoded columns, tags) tuples
     *
     * An encoded column is the concat of mv_assessment_relation columns:
     *     * folder id
     *     * course implementation id
     *     * course unit implementation id
     *     * course specification id
     *     * course unit specification id
     *
     * Each tuple equates to a set of tags
     *
     * @var array
     */
    private array $lookup = [];


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
        FROM mv_assessment_relation
        WHERE assessment_id = :assessmentId
        LIMIT 1
    ';


    /**
     * For finding the (name, uuid) tuple of a course spec
     */
    private const QUERY_COURSE_SPEC = '
        SELECT f.name, cs.uuid 
        FROM course_specification cs
        LEFT JOIN folder f ON cs.folder_id = f.id
        WHERE cs.id = :id
    ';


    /**
     * For finding the (name, uuid) tuple of a course unit spec
     */
    private const QUERY_COURSE_UNIT_SPEC = '
        SELECT f.name, cus.uuid 
        FROM course_unit_specification cus
        LEFT JOIN folder f ON cus.folder_id = f.id
        WHERE cus.id = :id
    ';


    /**
     * For finding the (name, uuid) tuple of a course spec
     * via a course impl
     */
    private const QUERY_COURSE_IMPL = '
        SELECT f.name, cs.uuid 
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
        SELECT f.name, cus.uuid 
        FROM course_unit_implementation cui
        INNER JOIN course_unit_specification cus ON cui.course_unit_specification_id = cus.id
        LEFT JOIN folder f ON cus.folder_id = f.id
        WHERE cui.id = :id
    ';


    /**
     * For finding the (name, code) tuple of a folder
     */
    private const QUERY_FOLDER = '
        SELECT code
        FROM folder_code
        WHERE folder_id = :id
    ';


    /* For prepared statements
     */
    private \PDOStatement $mvRelationStatement;
    private \PDOStatement $courseSpecStatement;
    private \PDOStatement $courseUnitSpecStatement;
    private \PDOStatement $courseImplStatement;
    private \PDOStatement $courseUnitImplStatement;
    private \PDOStatement $folderStatement;

    /**
     * Constructor.
     *
     * @param IConnector $db
     * @param TagFactory $tagFactory
     */
    public function __construct(private IConnector $db, private TagFactory $tagFactory)
    {
        $this->mvRelationStatement= $this->db->prepare(self::QUERY_MV_RELATION);
        $this->courseSpecStatement = $this->db->prepare(self::QUERY_COURSE_SPEC);
        $this->courseUnitSpecStatement = $this->db->prepare(self::QUERY_COURSE_UNIT_SPEC);
        $this->courseImplStatement = $this->db->prepare(self::QUERY_COURSE_IMPL);
        $this->courseUnitImplStatement = $this->db->prepare(self::QUERY_COURSE_UNIT_IMPL);
        $this->folderStatement = $this->db->prepare(self::QUERY_FOLDER);
    }


    /**
     * Find the set of tuples that corresponds to the context of the $assessmentId
     * These tuples' data are fed into TagFactory to produce the actual tags themselves.
     *
     * @param int $assessmentId
     * @return array
     * @throws Exception
     */
    public function find(int $assessmentId): array
    {
        $row = $this->findRow($assessmentId);

        /* Try finding it in $this->lookup first; if it's not there
         * we need to fetch it
         */
        $key = $this->encodeRow($row);

        if (isset($this->lookup[$key])) {
            return $this->lookup[$key];
        }

        /* Otherwise,
         * Look up $assessmentId the row in mv_assessment_relation
         * Interpret the columns
         */

        /* If course_specification_id is set, find out its name and uuid
         * If course_unit_specification_id is set, find out its name and uuid
         */
        if ($row['course_specification_id'] && $row['course_unit_specification_id']) {
            $courseSpec = $this->findCourseSpec((int)$row['course_specification_id']);
            $courseUnitSpec = $this->findCourseUnitSpec((int)$row['course_unit_specification_id']);

            /* Generate the set of tags and remember it in $this->lookup for
             * this particular combination of columns
             */
            $tags = $this->tagFactory->produce($courseSpec['uuid'], $courseUnitSpec['uuid'], null);
            $this->lookup[$key] = $tags;

            return $this->lookup[$key];
        }

        /* If course_implementation_id is set, find out its corresponding spec's name and uuid
         * If course_unit_implementation_id is set, find out its corresponding spec's name and uuid
         */
        if ($row['course_implementation_id'] && $row['course_unit_implementation_id']) {
            $courseSpec = $this->findCourseSpecViaImpl((int)$row['course_implementation_id']);
            $courseUnitSpec = $this->findCourseUnitSpecViaImpl((int)$row['course_unit_implementation_id']);
            $folderCodes = $this->findFolderCodes((int)$row['folder_id']);

            /* Generate the set of tags and remember it in $this->lookup for
             * this particular combination of columns
             */
            $tags = $this->tagFactory->produce($courseSpec['uuid'], $courseUnitSpec['uuid'], $folderCodes);
            $this->lookup[$key] = $tags;

            return $this->lookup[$key];
        }

        /* If course_* is null and course_unit_* is null, then we're in a homepage context;
         * use the folder id to find the folder's name and uuid
         */
        $folderCodes = $this->findFolderCodes((int)$row['folder_id']);

        /* Generate the set of tags and remember it in $this->lookup for
         * this particular combination of columns
         */
        $tags = $this->tagFactory->produce(null, null, $folderCodes);
        $this->lookup[$key] = $tags;

        return $this->lookup[$key];
    }


    /**
     * Finds a particular row from of mv_assessment_relation, for $assessmentId
     *
     * @param int $assessmentId
     * @return array
     * @throws \PDOException
     */
    private function findRow(int $assessmentId): array
    {
        $this->mvRelationStatement->execute([
            'assessmentId' => $assessmentId
        ]);

        return $this->mvRelationStatement->fetch(\PDO::FETCH_ASSOC);
    }


    /**
     * Encodes the columns of $row into a string to use as a hash lookup
     *
     * @param array $row
     * @return string
     */
    private function encodeRow(array $row): string
    {
        return implode(',', array_values($row));
    }


    /**
     * Finds the (name, uuid) tuple for a course spec
     *
     * @param int $courseSpecId
     * @return mixed
     * @throws \PDOException
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
     * @return mixed
     * @throws \PDOException
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
     * @return mixed
     * @throws \PDOException
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
     * @return mixed
     * @throws \PDOException
     */
    private function findCourseUnitSpecViaImpl(int $courseUnitImplId): array
    {
        $this->courseUnitImplStatement->execute([
            'id' => $courseUnitImplId
        ]);

        return $this->courseUnitImplStatement->fetch(\PDO::FETCH_ASSOC);
    }


    /**
     * Finds the csv of folder codes for a folderId
     *
     * @param int $folderId
     * @return string
     * @throws \PDOException
     */
    private function findFolderCodes(int $folderId): string
    {
        $this->folderStatement->execute([
            'id' => $folderId
        ]);

        $codes = [];

        /* TODO Supprt multiple folder codes
         *  Do this as a single tag for now...?
         */
        while ($row = $this->folderStatement->fetch(\PDO::FETCH_ASSOC)) {
            $codes[] = $row['code'];
        }

        return implode(', ', $codes);
    }
}
