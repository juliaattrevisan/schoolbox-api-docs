<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Page\Homepage;

use AcceptanceTester;
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
        $this->tester->fillField(['css' => self::FORM . ' input[name="textBoxHeading"]'], $heading);
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
        // Need to figure out what the id of this rich text field SHOULD be
        // (Find ID of field)
        $this->tester->fillRichTextField(
            $this->tester->grabAttributeFrom(
                self::FORM . ' textarea[name^="textBoxBody"]',
                'id'
            ),
            $content
        );
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
        $this->tester->click(self::FORM_SAVE);
        return $this;
    }
}
