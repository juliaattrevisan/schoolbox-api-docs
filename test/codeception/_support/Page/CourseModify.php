<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Page;

/**
 * Represents the course modify page
 */
class CourseModify
{

    public const URL = '/course/modify/{course_id}';
    public const URL_ID_FRAGMENT = '{course_id}';

    public const SELECTOR_LOCATION = '[data-course-parent-selector]';
    public const SELECTOR_USE_FOLDER = '[data-selection] [data-select]';
    public const SELECTOR_SAVE_COURSE = '#update-course-form [type=submit][value="Save Course"]';


    /**
     *
     * @param int $courseId
     * @return string
     */
    public static function getUrl(int $courseId): string
    {
        return str_replace(self::URL_ID_FRAGMENT, (string)$courseId, self::URL);
    }
}
