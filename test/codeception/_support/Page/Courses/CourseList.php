<?php

declare(strict_types=1);

namespace Page\Courses;

use AcceptanceTester;

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
        $this->tester->click(['css' => 'a[class=icon-overflow][data-dropdown=courseDropdown-' . $courseDropdownNumber . ']']);
    }

    /**
     * click import into class
     */
    public function clickImportIntoClass(): void
    {
        $this->tester->click(['css' => 'a[href="/course/import/6/folder/0/0?returnUrl=L2NvdXJzZS9saXN0"]']);
    }
}
