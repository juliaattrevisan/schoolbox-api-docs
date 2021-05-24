<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Page\Quiz;

use AcceptanceTester;
use Codeception\Util\Locator;

class ReviewHorizontalForm
{
    public const URL = 'http://192.168.36.10/assess/reviewHorizontal/{assess_id}';
    public const URL_QUESTION = 'http://192.168.36.10/assess/reviewHorizontal/{assess_id}/{question_id}';


    /**
     * @var AcceptanceTester
     */
    protected $tester;

    public $className;
    public $classId;
    public $quizTitle;

    public function __construct(AcceptanceTester $i)
    {
        $this->tester = $i;
    }

    /**
     * Generates route for this page.
     * @param int $assessId
     * @param int $questionId
     * @return string
     */
    public function generateRoute(int $assessId, int $questionId): string
    {
        return str_replace(['{assess_id}', '{question_id}'], [$assessId, $questionId], self::URL);
    }

    /**
     * Finalises current review
     * @throws \Exception
     */
    public function finalise()
    {
        $i = $this->tester;
        $i->waitForElementVisible('a.button.submit', 2);
        $i->click('a.button.submit');
        $i->waitForText('Status of ' . $this->quizTitle);
    }
}
