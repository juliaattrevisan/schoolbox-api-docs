<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Tests\Support\Page\Quiz;

use Tests\Support\AcceptanceTester;

/**
 * Quiz page object
 */
class AssessmentPage
{
    public const URL = '/learning/assessments/{assessment_id}/{user_id}';
    public const LINK_JELLY_TOT = '[data-test="dropdown-button-header-options"]';
    public const LINK_MODIFY = '#header-options a.icon-modify';

    /**
     * @var AcceptanceTester
     */
    protected $tester;

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
     * Generates route for this page.
     *
     * @param int $assessmentId
     * @param int $userId
     * @return string
     */
    public function generateRoute(int $assessmentId, int $userId): string
    {
        return str_replace(['{assessment_id}', '{user_id}'], [$assessmentId, $userId], self::URL);
    }

    /**
     * Navigates to modify page
     * @return AssessmentForm
     * @throws \Exception
     */
    public function clickModify(): AssessmentForm
    {
        $i = $this->tester;
        $i->click(self::LINK_JELLY_TOT);
        $i->click(self::LINK_MODIFY);
        $i->waitForText('Finish Modifying');

        // Jelly tot
        $i->click(self::LINK_JELLY_TOT);
        $i->click(self::LINK_MODIFY);
        $i->waitForElement(AssessmentForm::SELECTOR_BUTTON_UPDATE);

        $quizForm = new AssessmentForm($i);
        return $quizForm;
    }
}
