<?php

/**
 * @author      Julia Trevisan <julia.trevisan@alaress.com.au>
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Tests\Support\Page\LessonPlan;

use Tests\Support\AcceptanceTester;

class TemplateList
{
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
     * click unit arrow
     */
    public function clickTemplateLink($lessonPlanTemplateId): void
    {
        $this->tester->click(['css' => 'a[data-reveal-id="lpt-preview-' . $lessonPlanTemplateId . '"][title="Preview this template"]']);
    }
}
