<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Page\Survey;

use AcceptanceTester;

/**
 * Question Bank page object
 */
class QuestionBankPage
{
    public const URL = '/survey/questionbank/contents/{category_id}/{assess_id}/{section_id}';

    public const CATEGORY_ELECTIVE_SELECTION = 7;
    public const CATEGORY_MEDICAL = 9;

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
        return str_replace(['{assess_id}'], [$assessId], self::URL);
    }

    /**
     * Selects question bank category
     * @param int $categoryId
     * @throws \Exception
     */
    public function clickCategory(int $categoryId)
    {
        $i = $this->tester;
        $i->executeJS('$("#node_' . $categoryId . '_anchor").click()');
        $i->waitForText('ELECTIVE SELECTION');
    }

    /**
     * Clicks modify button of a question in question bank
     * @param string $question
     * @return QuestionForm
     * @throws \Exception
     */
    public function clickModify(string $question): QuestionForm
    {
        $i = $this->tester;
        $numQuestionBank = $i->grabNumRecords('assess_question_bank');
        $position = 0;
        for ($j = 1; $j <= $numQuestionBank; $j++) {
            $q = $i->grabTextFrom('#questionBankForm > section > ul > li:nth-child(' . $j . ')');
            if (strpos($q, $question) !== false) {
                $position = $j;
                break;
            }
        }

        $i->click('#questionBankForm > section > ul > li:nth-child(' . ($position) . ') > nav > a.icon-modify');
        $i->waitForElement('#createQuestionForm input[type=submit][value="Modify"]');

        return new QuestionForm($i);
    }
}
