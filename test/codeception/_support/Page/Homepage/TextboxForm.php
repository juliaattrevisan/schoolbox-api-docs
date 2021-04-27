<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Page\Homepage;

use AcceptanceTester;
use Codeception\Util\Locator;
use Page\Common;

/**
 * Class TextboxForm
 */
class TextboxForm
{
    public const URL = '/homepage/{homepageId}/textBoxCreate/{componentId}#go-to{componentId}';

    public const FORM = Common::ELEMENT_CONTENT . ' form[id^="textBoxForm"]';

    public const FORM_SAVE = self::FORM . ' input[type="submit"]';

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
     *
     * @param int $homepageId
     * @param int $componentId
     * @return string
     */
    private function generateRoute(int $homepageId, int $componentId): string
    {
        return strtr(static::URL, ['{homepageId}' => $homepageId, '{componentId}' => $componentId]);
    }

    /**
     * @param string $heading
     * @return $this
     */
    public function fillHeading(string $heading): self
    {
        $this->tester->fillField("heading", $heading);
        return $this;
    }

    /**
     * @param string $heading
     * @return $this
     */
    public function seeInHeading(string $heading): self
    {
        $this->tester->seeInField(['css' => self::FORM . ' input[name="textBoxHeading"]'], $heading);
        return $this;
    }

    /**
     * @param string $content
     * @return $this
     */
    public function fillContent(string $content): self
    {
        $this->tester->executeJS('for(var i in CKEDITOR.instances) { CKEDITOR.instances[i].setData(\''.$content.'\')}');
        return $this;
    }

    /**
     * @param string $content
     * @return $this
     */
    public function seeInContent(string $content): self
    {
        // Need to figure out what the id of this rich text field SHOULD be
        // (Find ID of field)
        $this->tester->seeInRichTextField(
            $this->tester->grabAttributeFrom(
                self::FORM . ' textarea[name^="textBoxBody"]',
                'id'
            ),
            $content
        );
        return $this;
    }

    /**
     * @return $this
     */
    public function submit(): self
    {
        $this->tester->click(['css' => 'input[type=submit][value=Create\ Text\ Box]']);
        sleep(2);
        return $this;
    }

    /**
     * @return $this
     * @throws \Exception
     */
    public function save(): self
    {
        $this->tester->scrollTo(['css' => 'div[class=component-container\ Schoolbox_Resource_Textbox_Component_Homepage_Controller]']);
        $element = Locator::contains('div.component-action section button', 'Save');
        $this->tester->waitForElementVisible($element, 5);
        $this->tester->click($element);
        return $this;
    }
}
