<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Page\Quiz;

use AcceptanceTester;

/**
 * Review quiz attempt form
 * @package Page\Quiz
 */
class ReviewForm
{
    public const URL = '/assess/review/{assess_id}/{section_id}/{attempt_id}'; // TODO: not sure...

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
     * @param int $attemptId
     * @return string
     */
    public function generateRoute(int $assessId, int $sectionId, int $attemptId): string
    {
        return str_replace(['{assess_id}', '{section_id}', '{attempt_id}'], [$assessId, $sectionId, $attemptId], self::URL);
    }

    /**
     * Finalises the review
     * @throws \Exception
     */
    public function finalise()
    {
        $i = $this->tester;
        $i->click('Finalise Review');
        $i->click('Yes');
        $i->waitForText('Status of ' . $this->quizTitle);
    }
}
