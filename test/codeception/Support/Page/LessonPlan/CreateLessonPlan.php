<?php

/**
 * @author      Julia Trevisan <julia.trevisan@alaress.com.au>
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Tests\Support\Page\LessonPlan;

use Tests\Support\AcceptanceTester;

class CreateLessonPlan
{
    private AcceptanceTester $tester;

    public const CREATE_BUTTON = 'input[type=submit][value=Create]';

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
     * click unit arrow
     */
    public function typeTitle($lessonPlanName): void
    {
        $this->tester->fillField('input#title', $lessonPlanName);
    }

    /**
     * click Create button
     */
    public function clickCreateButton(): void
    {
        $this->tester->click(self::CREATE_BUTTON);
    }
}
