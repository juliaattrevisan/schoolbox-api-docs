<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Tests\Support\Page\Duework;

use Tests\Support\AcceptanceTester;

/**
 * Duework page object
 */
class AssessmentPage
{
    public const URL = '/learning/assessments/{assessment_id}/{user_id}';
    public const LINK_JELLY_TOT = '[data-test=wc-actions-dropdown-header-options]';
    public const LINK_JELLY_TOT_BUTTON = '[data-test=wc-actions-dropdown-header-options-button]';
    public const LINK_MODIFY = '#header-options a.icon-modify';
    public const LINK_STATUS = '#header-options a.icon-progress';

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
        $i->clickElementInWebComponent(self::LINK_JELLY_TOT, self::LINK_JELLY_TOT_BUTTON);
        $i->click(self::LINK_MODIFY);
        $i->waitForElement(AssessmentForm::SELECTOR_BUTTON_UPDATE);

        $dueworkForm = new AssessmentForm($i);
        return $dueworkForm;
    }

    /**
     * Navigates to inline grader for the student
     * @param string $student
     * @param string $title
     * @return FeedbackForm
     * @throws \Exception
     */
    public function openInlineGrader(string $student, string $title): FeedbackForm
    {
        $i = $this->tester;
        $i->click('Submission by ' . $student, '#content section[class="submission-history"]');
        $i->waitForText('Grading ' . $title);

        $feedbackForm = new FeedbackForm($i);
        return $feedbackForm;
    }

    /**
     * Navigates to duework status page
     */
    public function openStatusPage(): StatusPage
    {
        $i = $this->tester;
        $i->clickElementInWebComponent(self::LINK_JELLY_TOT, self::LINK_JELLY_TOT_BUTTON);
        $i->click(self::LINK_STATUS);

        return new StatusPage($i);
    }
}
