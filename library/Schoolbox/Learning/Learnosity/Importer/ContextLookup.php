<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Learning\Learnosity\Importer;

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
     * Find the set of tuples that corresponds to the context of the $assessmentId
     * These tuples' data are fed into TagFactory to produce the actual tags themselves.
     *
     * @param int $assessmentId
     * @return array
     */
    public function find(int $assessmentId): array
    {
        /* Try finding it in $this->lookup first; if it's not there
         * we need to fetch it
         */

        /* Otherwise,
         * Look up $assessmentId the row in mv_assessment_relation
         */

        /* Interpret the columns
         */

        /* If course_specification_id is set, find out its name and uuid
         * If course_unit_specification_id is set, find out its name and uuid
         */

            /* Generate the set of tags and remember it in $this->lookup for
             * this particular combination of columns
             */

        /* If course_implementation_id is set, find out its corresponding spec's name and uuid
         * If course_unit_implementation_id is set, find out its corresponding spec's name and uuid
         */

            /* Generate the set of tags and remember it in $this->lookup for
             * this particular combination of columns
             */

        /* If course_* is null and course_unit_* is null, then we're in a homepage context;
         *  use the folder id to find the folder's name and uuid
         */

            /* Generate the set of tags and remember it in $this->lookup for
             * this particular combination of columns
             */

        return [];
    }
}
