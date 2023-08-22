<?php

declare(strict_types=1);

namespace Tests\Support\Page\Courses;

use Tests\Support\AcceptanceTester;

class CourseList
{
    public const URL = '/course/list';

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
     * click a course 3-dot menu
     */
    public function clickCourseDotMenu($courseDropdownNumber): void
    {
        $this->tester->click(['css' => '[data-test="dropdown-button-' . $courseDropdownNumber . '"]']);
    }


    /**
     * click a course down arrow by passing the unit specification id
     */
    public function clickCourseDownArrow($courseId): void
    {
        $this->tester->click(['css' => 'a[class=icon-down-arrow][data-collapser=course-' . $courseId . ']']);
    }

    /**
     * click import into class
     */
    public function clickImportIntoClass($courseDropdownNumber): void
    {
        $this->tester->click(['css' => sprintf('a[href="/course/import/%s/folder/0/0?returnUrl=L2NvdXJzZS9saXN0"]', $courseDropdownNumber)]);
    }
}
