<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Page\Homepage;

use AcceptanceTester;
use Codeception\Util\Locator;
use Page\Common;

class ManageAllTextbox
{
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
     * click Manage All button on the textbox component
     */
    public function clickManageAll(): void
    {
        $this->tester->click('Manage All');
        $this->tester->waitForText("Manage Text Boxes");
    }

    /**
     * click Create Text Box button
     */
    public function clickCreateTextBox(): void
    {
        $this->tester->click('a.icon-add.button');
    }

    /**
     * type a heading text
     * @param string $headingText
     * @param int $textBoxItem
     * @return $this
     */
    public function typeTextBoxHeading(int $textBoxItem, string $headingText): self
    {
        $this->tester->fillField('input[name="textbox[' . $textBoxItem . '][heading]"]', $headingText);
        return $this;
    }

    /**
     * enter a textbox content
     * @param string $contentText
     * @param int    $textBoxItem
     * @return $this
     * @throws \Exception
     */
    public function typeTextBoxContent(int $textBoxItem, string $contentText,): self
    {
        $this->tester->fillCkEditorById('textbox-' . $textBoxItem . '-body', $contentText);
        return $this;
    }

    /**
     * click Save All button
     */
    public function clickSaveAll(): void
    {
        $this->tester->click('input[type=submit]');
    }
}
