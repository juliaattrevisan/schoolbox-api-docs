<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Page\Survey;

use AcceptanceTester;
use Codeception\Util\Locator;

/**
 * Survey Summary page object
 */
class SummaryPage
{
    public const URL = '/survey/summary/{assess_id}';

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
     * @param int $assessId
     * @return string
     */
    public function generateRoute(int $assessId): string
    {
        return str_replace('{assess_id}', (string)$assessId, self::URL);
    }

    /**
     * Navigates to summary page
     * @param int $assessId
     * @param string $surveyTitle
     * @throws \Exception
     */
    public function open(int $assessId, string $surveyTitle)
    {
        $this->tester->amOnPage($this->generateRoute($assessId));
        $this->tester->waitForText($surveyTitle);
    }

    /**
     * Adds a question to Question Bank
     * @param int $questionId
     * @param int $categoryId
     * @throws \Exception
     */
    public function addToQuestionBank(int $questionId, int $categoryId)
    {
        $i = $this->tester;
        $i->executeJs('$("#question_' . $questionId . ' a.icon-download").click()');
        $i->waitForElementVisible('#question-bank');
        // Select Question Category
        $i->executeJS('$("#node_' . $categoryId . '_anchor").click()');
        $i->click('input[type=submit][value="Add To Question Bank"]');
        $i->waitForText('Question added to the Questionbank');
        $i->reloadPage();
    }

    /**
     * Clicks Add from Question bank for the given section
     * @param $sectionId
     * @return QuestionBankPage
     * @throws \Exception
     */
    public function clickAddFromQuestionBank(int $sectionId): QuestionBankPage
    {
        $i = $this->tester;

        // Jelly Tot!
//        $i->click(Locator::elementAt('#content a.icon-more[data-dropdown="section' . $sectionId . '-options"]', 2));
        $i->click('#content a.icon-more[data-dropdown="section' . $sectionId . '-options"]');
        // Add from Question Bank
        $i->click('#section' . $sectionId . '-options a.icon-download');

        $i->waitForText('CATEGORIES');
        return new QuestionBankPage($i);
    }

    /**
     * Clicks New Question for the given section
     * @param int $sectionId
     * @return QuestionForm
     * @throws \Exception
     */
    public function clickNewQuestion(int $sectionId): QuestionForm
    {
        $i = $this->tester;

        // Jelly Tot!
//        $i->click(Locator::elementAt('#content a.icon-more[data-dropdown="section' . $sectionId . '-options"]', 2));
        $i->click('#content a.icon-more[data-dropdown="section' . $sectionId . '-options"]');
        // Add from Question Bank
        $i->click('#section' . $sectionId . '-options a.icon-add');

        $i->waitForText('CREATE QUESTION');
        return new QuestionForm($i);
    }
}
