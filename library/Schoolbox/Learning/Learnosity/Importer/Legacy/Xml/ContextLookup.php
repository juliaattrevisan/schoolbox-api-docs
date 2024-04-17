<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Importer\Legacy\Xml;

use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Learning\Learnosity\Importer\IContextLookup;
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
class ContextLookup implements IContextLookup
{
    /**
     * Constructor.
     *
     * @param TagFactory        $tagFactory
     * @param AssessmentContext $context
     */
    public function __construct(
        private TagFactory $tagFactory,
        private AssessmentContext $context
    ) {
    }


    /**
     * Find the set of tuples that corresponds to the context of the $assessmentId
     * These tuples' data are fed into TagFactory to produce the actual tags themselves.
     *
     * @param int $assessmentId
     * @return array
     * @throws \PDOException
     */
    public function find(int $assessmentId): array
    {
        if ($this->context instanceof \Schoolbox\Learning\Course\DataModel\Specification\Unit\Model) {
            /* Course Specification, when authoring a course's contents
             */
            return [];
        } elseif ($this->context instanceof \Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model) {
            /* Course Implementation, when you randomly add to an already-imported course
             */
            return [];
        }

        /* Directly into a homepage via the quiz component
         */
        $folderCodes = $this->findFolderCodes($this->context);

        /* Generate the set of tags and remember it in $this->lookup for
         * this particular combination of columns
         */
        $tags = $this->tagFactory->produce(null, null, $folderCodes);

        /* Use this tag so we know it's an impl
         */
        $tags[] = $this->tagFactory->produceImplFlag();

        return $tags;

        ///////////
        $row = $this->findRow($assessmentId);

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

            /* Use this tag so we know it's an impl
             */
            $tags[] = $this->tagFactory->produceImplFlag();

            $this->lookup[$key] = $tags;

            return $this->lookup[$key];
        }

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
     * @return array
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
     * @return array
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
     * @return array
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
     * @return array
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
     * @param AssessmentContext $context
     * @return string
     * @throws \PDOException
     */
    private function findFolderCodes(AssessmentContext $context): string
    {
        /* TODO Support multiple folder codes
         *  Do this as a single tag for now...?
         */
        return $context->getFolder()->getCodeString();
    }
}
