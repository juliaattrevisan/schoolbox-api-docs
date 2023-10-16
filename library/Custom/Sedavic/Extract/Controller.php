<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Custom\Sedavic\Extract;

use Alaress\Web\Controller\Base;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\UserMapper;

class Controller extends Base
{
    /**
     * Controller constructor.
     *
     * @param ErrorMapper $errorMapper
     * @param UserMapper  $userMapper
     */
    public function __construct(private ErrorMapper $errorMapper, private UserMapper $userMapper)
    {
    }


    /**
     * Provide form
     */
    public function getExtract(): void
    {
        /* Find a list of student ids to process one at a time
         */

        /* For every student, get all the stuff they need
         *
         * First obtain their basic user details: name, identifiers, house and year level
         * names - because we're reusing this info per curriculum node and there'll be
         * a lot of those
         */

        /* Find all the assessment info from the v_assessment_feedback table
         * This contains all the info you need to find their marks, for both
         * the normalised- and human-readable marks
         */

        /* Find all the curriculum codes associated with the assessments separately
         * since you kinda need the full hierarchy of a curriculum node
         */

        /* Find all the ancestry info about the assessment: course, unit
         */

        /* Combine all that into a csv row
         */

        /* Pump it to an fputcsv
         */
    }
}
