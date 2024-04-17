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
            $courseSpec = $this->findCourseSpec($this->context);
            $courseUnitSpec = $this->findCourseUnitSpec($this->context);

            /* Generate the set of tags and remember it in $this->lookup for
             * this particular combination of columns
             */
            return $this->tagFactory->produce($courseSpec['uuid'], $courseUnitSpec['uuid'], null);
        } elseif ($this->context instanceof \Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model) {
            /* Course Implementation, when you randomly add to an already-imported course
             */
            $courseSpec = $this->findCourseSpecViaImpl($this->context);
            $courseUnitSpec = $this->findCourseUnitSpecViaImpl($this->context);
            $folderCodes = $this->findFolderCodes($this->context->getCourse()->getFolder());

            /* Generate the set of tags and remember it in $this->lookup for
             * this particular combination of columns
             */
            $tags = $this->tagFactory->produce($courseSpec['uuid'], $courseUnitSpec['uuid'], $folderCodes);

            /* Use this tag so we know it's an impl
             */
            $tags[] = $this->tagFactory->produceImplFlag();

            return $tags;
        }

        /* Directly into a homepage via the quiz component
         */
        $folderCodes = $this->findFolderCodes($this->context->getFolder());

        /* Generate the set of tags and remember it in $this->lookup for
         * this particular combination of columns
         */
        $tags = $this->tagFactory->produce(null, null, $folderCodes);

        /* Use this tag so we know it's an impl
         */
        $tags[] = $this->tagFactory->produceImplFlag();

        return $tags;
    }


    /**
     * Finds the (name, uuid) tuple for a course spec
     *
     * @param AssessmentContext $context
     * @return array
     * @throws \PDOException
     */
    private function findCourseSpec(AssessmentContext $context): array
    {
        return [
            /* @phpstan-ignore-next-line */
            'name' => $context->getCourse()->getFolder()->getName(),
            /* @phpstan-ignore-next-line */
            'uuid' => $context->getCourse()->getUuid(),
        ];
    }


    /**
     * Finds the (name, uuid) tuple for a course unit spec
     *
     * @param AssessmentContext $context
     * @return array
     * @throws \PDOException
     */
    private function findCourseUnitSpec(AssessmentContext $context): array
    {
        return [
            'name' => $context->getFolder()->getName(),
            /* @phpstan-ignore-next-line */
            'uuid' => $context->getUuid(),
        ];
    }


    /**
     * Finds the (name, uuid) spec tuple for a course impl
     *
     * @param AssessmentContext $context
     * @return array
     * @throws \PDOException
     */
    private function findCourseSpecViaImpl(AssessmentContext $context): array
    {
        return [
            /* @phpstan-ignore-next-line */
            'name' => $context->getSpecification()->getCourse()->getFolder()->getName(),
            /* @phpstan-ignore-next-line */
            'uuid' => $context->getSpecification()->getCourse()->getUuid()
        ];
    }


    /**
     * Finds the (name, uuid) spec tuple for a course unit impl
     *
     * @param AssessmentContext $context
     * @return array
     * @throws \PDOException
     */
    private function findCourseUnitSpecViaImpl(AssessmentContext $context): array
    {
        return [
            /* @phpstan-ignore-next-line */
            'name' => $context->getSpecification()->getCourse()->getFolder()->getName(),
            /* @phpstan-ignore-next-line */
            'uuid' => $context->getSpecification()->getUuid()
        ];
    }


    /**
     * Finds the csv of folder codes for a fully hydrated $folder instance
     *
     * @param \Schoolbox\Resource\Folder\Model $folder
     * @return string
     * @throws \PDOException
     */
    private function findFolderCodes(\Schoolbox\Resource\Folder\Model $folder): string
    {
        /* TODO Support multiple folder codes
         *  Do this as a single tag for now...?
         */
        return $folder->getCodeString();
    }
}
