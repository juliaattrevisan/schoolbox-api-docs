<?php

namespace Page\Admin\LMS\LessonPlanTemplates;

use AcceptanceTester;

class LessonPlanTemplateList
{
    public const URL = '/adminv2/lesson-plan/template';

    public const ICON_ADD = 'a.icon-add';

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
     * click add lesson plan template icon
     */
    public function clickAddLessonPlanTemplateIcon(): void
    {
        $this->tester->click(self::ICON_ADD);
    }
}
