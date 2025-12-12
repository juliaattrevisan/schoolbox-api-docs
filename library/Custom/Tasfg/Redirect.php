<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

namespace Custom\Tasfg;

use Alaress\Database\Exception;
use Alaress\Database\Exception as DatabaseException;
use External\Canvas\Redirect\AbstractRedirect;
use PDOException;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use Schoolbox\User;

/**
 * Redirect from schoolbox homepage to canvas somewhere
 * by checking the folder code against the local canvas db cache
 *
 * If there's a matching course, manufacture a url to that place in
 * canvas instance; otherwise return null
 */
class Redirect extends AbstractRedirect
{
    /**
     * Tara identifier
     */
    public const SITE_SCHOOL_CODE = 'tasfg';

    /**
     * Canvas course id is <year> <course> <class suffix>
     */
    protected string $courseIdFormat = '%s %s';

    /**
     * The column used to find canvas course in course table
     */
    protected string $courseCodeColumn = "sis_source_id";
}
