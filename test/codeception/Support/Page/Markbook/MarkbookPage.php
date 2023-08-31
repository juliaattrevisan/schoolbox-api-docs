<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Tests\Support\Page\Markbook;

use Tests\Support\AcceptanceTester;
use Tests\Support\Page\Common;

/**
 * Markbook form page object
 */
class MarkbookPage
{
    const REPORT_CONTENT = '#report_content';
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
     * Navigates to markbook page
     * @param $classId
     * @return MarkbookPage
     * @throws \Exception
     */
    public function open($classId): self
    {
        $i = $this->tester;
        // back to homepage
        // TODO: change to use Tests\Support\Page/Homepage
        $i->amOnPage('/homepage/' . $classId);

        // Jelly tot
        $i->clickElementInWebComponent(Common::SELECTOR_WC_ACTIONS_DROPDOWN, Common::SELECTOR_WC_ACTIONS_DROPDOWN_BUTTON);
        // Markbook
        $i->click('#tab-control a.icon-curriculum');
        $i->waitForElement('div[data-loaded="1"]');
        $i->click('li[data-component="12"]');
        $i->waitForElement('label[for="filter_selection_12_1"]');
        $i->click('label[for="filter_selection_12_1"]');
        sleep(1);
        $i->click('li[data-component="10"]');
        $i->waitForElement('label[for="filter_selection_10_open"]');
        $i->click('label[for="filter_selection_10_open"]');
        $i->click('li[data-component="10"]');
        sleep(1);
        $i->click('input#submit_button');
        return $this;
    }

    /**
     * Verifies the given assessment title exists on markbook page
     * @param string $title
     */
    public function canSeeAssessment(string $title)
    {
        $this->tester->canSee($title, self::REPORT_CONTENT);
    }

    /**
     * Verifies the given name exists on markbook page
     * @param string $name
     */
    public function canSeeParticipant(string $name)
    {
        $this->tester->canSee(self::surnameFirst($name), self::REPORT_CONTENT);
    }

    /**
     * Verifies the given name does not exist on markbook page
     * @param string $name
     */
    public function cantSeeParticipant(string $name)
    {
        $this->tester->cantSee(self::surnameFirst($name), self::REPORT_CONTENT);
    }

    private static function surnameFirst($fullname): string
    {
        $name = str_replace(['Mr ', 'Mrs '], '', $fullname);
        $parts = explode(' ', $name);
        return $parts[1] . ', ' . $parts[0];
    }
}
