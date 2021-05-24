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
 * Duework feedback form page object
 */
class FeedbackForm
{
    public const URL = '/learning/assessments/{assessment_id}/{user_id}/responses/{response_id}';

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
        return str_replace('{assess_id}', $assessId, self::URL);
    }

    /**
     * Sets grade
     * @param $grade
     * @return FeedbackForm
     */
    public function setGrade($grade): self
    {
        $i = $this->tester;
        $i->fillField('input[name="mark"]', $grade);
        return $this;
    }

    /**
     * Sets comment
     * @param string $comment
     * @return FeedbackForm
     */
    public function setComment(string $comment): self
    {
        $i = $this->tester;
        $i->fillField('textarea[name="comment"]', Common::gentext(200));
        $i->wait(random_int(1, 3));
        $i->fillField('textarea[name="comment"]', Common::gentext(200));
        $i->wait(random_int(1, 3));
        $i->fillField('textarea[name="comment"]', $comment);
        $i->wait(random_int(1, 3));
        return $this;
    }

    /**
     * Sets attachment
     * @param string $filename
     * @return FeedbackForm
     */
    public function setAttachment(string $filename): self
    {
        $this->tester->attachAttachzoneFile('#FeedbackFilesZone', $filename);
        return $this;
    }

    /**
     * Publishes the feedback
     * @return FeedbackForm
     * @throws \Exception
     */
    public function publish(): self
    {
        $i = $this->tester;
        // wait until the change is saved
        $i->waitForText('SAVED AS DRAFT');
        $i->wait(2); // this is to wait for auto saving to finish
        $i->click('Publish And Continue');
        $i->waitForText('PUBLISH AND CONTINUE'); // for next student
        return $this;
    }
}
