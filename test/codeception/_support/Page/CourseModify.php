<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Page;

use AcceptanceTester;

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

    private AcceptanceTester $tester;

    /**
     * Constructor
     *
     * @param AcceptanceTester $i
     */
    public function __construct(AcceptanceTester $i)
    {
        $this->tester = $i;
    }
    /**
     *
     * @param int $courseId
     * @return string
     */
    public static function getUrl(int $courseId): string
    {
        return str_replace(self::URL_ID_FRAGMENT, (string)$courseId, self::URL);
    }

    /**
     * click unit arrow
     */
    public function clickUnitArrow($unitNumber): void
    {
        $this->tester->click(['css' => 'a[class=icon-down-arrow][data-collapser=unit-' . $unitNumber . ']']);
    }

    /**
     * click export icon
     */
    public function clickExportIcon($assessmentNumber): void
    {
        $this->tester->click(['css' => 'a[href="/learning/export/assessment/' . $assessmentNumber . '?returnUrl=L2NvdXJzZS9tb2RpZnkvNiMxNQ%3D%3D"]']);
    }
}
