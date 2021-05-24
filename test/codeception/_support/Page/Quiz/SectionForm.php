<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Page\Quiz;

use AcceptanceTester;

/**
 * Quiz Section form page object
 */
class SectionForm
{
    public const URL = '/assess/section/create/{assess_id}';

    public const FORM = '#createSection';
    public const SELECTOR_TITLE = self::FORM . ' input[name="title"]';
    public const SELECTOR_BUTTON_CREATE = self::FORM . ' [type=submit][value="Create Section"]';
    public const SELECTOR_SECTION_ID = 'input[name="section_id"]';

    /**
     * @var AcceptanceTester
     */
    protected $tester;

    private $title;

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
     * Sets title
     * @param string $title
     * @return SectionForm
     */
    public function setTitle(string $title): self
    {
        $this->title = $title;
        $this->tester->fillField(self::SELECTOR_TITLE, $title);
        return $this;
    }

    /**
     * Submits this form to create the section
     * @return SectionForm
     * @throws \Exception
     */
    public function create(): self
    {
        $this->tester->click(self::SELECTOR_BUTTON_CREATE);
        $this->tester->waitForElement(self::SELECTOR_SECTION_ID);
        return $this;
    }

    /**
     * Gets section id from current form
     * @return mixed
     */
    public function getSectionId()
    {
        $sectionId = $this->tester->grabValueFrom(self::SELECTOR_SECTION_ID);
        return $sectionId;
    }

    /**
     * Finalises current review
     * @throws \Exception
     */
    public function finalize()
    {
        $i = $this->tester;
        $i->click($this->title);
        $i->waitForText('Finish Modifying');
        $i->click('Finish Modifying');
    }
}
