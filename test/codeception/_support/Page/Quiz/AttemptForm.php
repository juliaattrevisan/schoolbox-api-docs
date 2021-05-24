<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Page\Quiz;

use AcceptanceTester;

/**
 * Quiz attempt form
 * Class AttemptForm
 * @package Page\Quiz
 */
class AttemptForm
{
    public const URL = '/assess/attempt/{assess_id}/{attempt_id}';

    /**
     * @var AcceptanceTester
     */
    protected $tester;

    public $className;
    public $classId;

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
    public function generateRoute(int $assessId, int $attemptId): string
    {
        return str_replace(['{assess_id}', '{attempt_id}'], [$assessId, $attemptId], self::URL);
    }

    /**
     * Creates attempt
     * @param String $quizTitle
     * @param String $contents
     * @return int
     * @throws \Exception
     */
    public function createAttempt(string $quizTitle, string $contents): int
    {
        // TODO: need more refactoring
        $i = $this->tester;
        $i->click($quizTitle, '#content');
        $i->waitForText('Begin Quiz');
        $i->click('Begin Quiz', '#content');
        $i->waitForText($quizTitle);
        $i->fillField('form input[name="answer"]', $contents);
        $i->click('Finish');
        $i->click('Yes');
        $i->waitForText('Quiz Completed');
        // http://192.168.36.10/assess/end/60/69
        $endUrl = $i->grabFromCurrentUrl();
        $pos = strrpos($endUrl, '/');
        $attemptId = intval(substr($endUrl, $pos + 1));
        return $attemptId;
    }
}
