<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Custom\Trigs;

use External\Canvas\Redirect\AbstractRedirect;

/**
 * Canvas redirect for Trigs
 */
class Redirect extends AbstractRedirect
{
    /**
     * Canvas course id is <folder_code>_<parent_folder_code>
     */
    protected string $courseIdFormat = '%s_%s';

    /**
     * The column used to find canvas course in course table
     */
    protected string $courseCodeColumn = "sis_source_id";
}
