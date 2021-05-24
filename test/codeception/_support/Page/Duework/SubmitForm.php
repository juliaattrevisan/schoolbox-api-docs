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
 * Duework submit form page object
 */
class SubmitForm
{
    public const URL = '/learning/assessments/{assessment_id}/{user_id}/submit';

    public const FORM = '#createSection';
    public const SELECTOR_BUTTON_SUBMISSION = '#editor-form [type=submit][value="Submit for assessment"]';

    /**
     * @var AcceptanceTester
     */
    protected $tester;

    private $filename;
    private $contents;

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
     * Sets editor contents
     * @param string $contents
     * @return SubmitForm
     */
    public function setContents(string $contents): self
    {
        $i = $this->tester;
        $i->fillCkEditorById('editor-body', Common::gentext(300));
        $i->wait(random_int(1, 3));
        $i->fillCkEditorById('editor-body', Common::gentext(300));
        $i->wait(random_int(1, 3));
        $i->fillCkEditorById('editor-body', $contents);
        $i->wait(random_int(1, 3));
        $this->contents = $contents;

        return $this;
    }

    /**
     * @param string $filename
     * @return SubmitForm
     */
    public function setAttachment(string $filename): self
    {
        $this->tester->attachAttachzoneFile('#files-zone', $filename);
        $this->filename = $filename;
        return $this;
    }

    /**
     * Submits this form to create the section
     * @return SubmitForm
     * @throws \Exception
     */
    public function submit(): self
    {
        $i = $this->tester;
        $i->waitForElementVisible(self::SELECTOR_BUTTON_SUBMISSION);
        $i->wait(1); // just to make sure that auto save is gone.
        $i->click(self::SELECTOR_BUTTON_SUBMISSION);
        $i->waitForText('SUBMISSION HISTORY');
        // verify
        if (!empty($this->contents)) {
            $i->canSee('Online Submission');
        }
        if (!empty($this->filename)) {
            $dot = strrpos($this->filename, '.');
            $slash = strrpos($this->filename, '/') + 1;
            $attachName = substr($this->filename, $slash, $dot - $slash);
            $i->canSee($attachName);
        }
        return $this;
    }
}
