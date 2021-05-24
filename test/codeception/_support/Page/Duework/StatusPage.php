<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Page\Duework;

use AcceptanceTester;
use Page\Common;

/**
 * Duework status page
 */
class StatusPage
{

    public const URL = '/learning/assessments/{assessment_id}/status';

    public const LINK_JELLY_TOT = '#content a.icon-more';
    public const LINK_REVIEW_HORIZONTAL = '#header-options a.icon-class-list';
    public const LINK_BULK_REVIEW = '#header-options a.icon-approve';
    public const LINK_MODIFY = '#header-options a.icon-modify';

    public const SELECTOR_MARK = 'input[name="mark"]';
    public const SELECTOR_COMMENT = 'textarea[name="comment"]';
    public const SELECTOR_BUTTON_PUBLISH = 'a[data-draft="publish"]';

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
    public static function getStatusSelector(int $studentId, string $fieldSelector)
    {

        return '#assessment-status li[data-participant="' . $studentId . '"] ' . $fieldSelector;
    }

    /**
     * Publishes feedback for the student on status page
     * @param int $studentId
     * @param $grade
     * @param $comment
     * @throws \Exception
     */
    public function gradeSubmission(int $studentId, $grade, $comment)
    {
        $i = $this->tester;
        $i->fillField(self::getStatusSelector($studentId, self::SELECTOR_MARK), $grade);
        $i->fillField(self::getStatusSelector($studentId, self::SELECTOR_COMMENT), $comment);
        $i->waitForElementVisible(self::getStatusSelector($studentId, self::SELECTOR_BUTTON_PUBLISH));
        $i->wait(2); // this is to wait for auto saving to finish
        $i->click(self::getStatusSelector($studentId, self::SELECTOR_BUTTON_PUBLISH));
        $i->waitForElementNotVisible(self::getStatusSelector($studentId, self::SELECTOR_BUTTON_PUBLISH));
    }
}
