<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Page\Quiz;

use AcceptanceTester;
use Codeception\Util\Locator;

/**
 * Quiz status page
 */
class StatusPage
{

    public const URL = '/learning/assessments/{assessment_id}/status';
    public const LINK_JELLY_TOT = '#content a.icon-more';
    public const LINK_REVIEW_HORIZONTAL = '#header-options a.icon-class-list';
    public const LINK_BULK_REVIEW = '#header-options a.icon-approve';

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
     * @param $assessmentId
     * @return mixed
     */
    public function generateRoute(int $assessmentId)
    {
        return str_replace('{assessment_id}', $assessmentId, self::URL);
    }

    /**
     * Gets selector for a given student
     * @param int $studentId
     * @param String $fieldSelector
     * @return string
     */
    public static function getStatusSelector(int $studentId, String $fieldSelector)
    {
        return '#assessment-status li[data-participant="' . $studentId . '"] ' . $fieldSelector;
    }

    /**
     * Navigates to review attempt page for single student
     * @param int $studentId
     * @param string $quizTitle
     * @param string $student
     * @return ReviewForm
     * @throws \Exception
     */
    public function clickReviewAttempt(int $studentId, string $quizTitle, string $student): ReviewForm
    {
        $i = $this->tester;
        $i->click('Review Attempt', StatusPage::getStatusSelector($studentId, ''));
        $i->waitForText('Marking ' . $quizTitle . ' quiz results for ' . $student);
        $reviewForm = new ReviewForm($i);
        $reviewForm->quizTitle = $quizTitle;
        return $reviewForm;
    }

    /**
     * Navigates to review horizontal page
     * @param string $quizTitle
     * @return ReviewHorizontalForm
     * @throws \Exception
     */
    public function clickReviewHorizontal(string $quizTitle): ReviewHorizontalForm
    {
        $i = $this->tester;
        $i->click(self::LINK_JELLY_TOT);
        $i->click(self::LINK_REVIEW_HORIZONTAL);
        $i->waitForText('Horizontal Marking for ' . $quizTitle);
        $i->click('Q1)');
        $i->waitForText('Horizontal Marking for ' . $quizTitle);
        $reviewHorizontalForm = new ReviewHorizontalForm($i);
        $reviewHorizontalForm->quizTitle = $quizTitle;
        return $reviewHorizontalForm;
    }

    /**
     * Performs bulk mark as reviewed
     * @param string $quizTitle
     * @throws \Exception
     */
    public function bulkReview(string $quizTitle)
    {
        $i = $this->tester;
        $i->click(self::LINK_JELLY_TOT);
        $i->click(self::LINK_BULK_REVIEW);
        $i->waitForText('Mark Attempts as Reviewed');
        $i->click('Mark All as Reviewed');
        $i->waitForText('Status of ' . $quizTitle);
    }

}
