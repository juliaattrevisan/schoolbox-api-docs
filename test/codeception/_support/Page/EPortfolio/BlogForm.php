<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Page\EPortfolio;

use AcceptanceTester;
use Page\Common;

/**
 * Class PageForm
 */
class BlogForm
{
    public const URL = '/eportfolio/blog/modify/new/{pageId}';

    public const FORM = Common::ELEMENT_CONTENT . ' #blogForm';

    public const SELECTOR_BUTTON_POST = self::FORM . ' input[type="submit"]';

    public const TEMPLATE_MY_LEARNING = 4;

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
     * @param int $userId
     * @return string
     */
    public function generateRoute(int $userId): string
    {
        return strtr(static::URL, ['{uesrId}' => $userId]);
    }

    public function open(int $homepageId): self
    {
        $this->tester->amOnPage($this->generateRoute($homepageId));
        return $this;
    }

    /**
     * @param string $title
     * @return $this
     */
    public function fillTitle(string $title): self
    {
        $this->tester->fillField(['css' => self::FORM . ' input[name="heading"]'], $title);
        return $this;
    }

    /**
     * @param string $title
     * @return $this
     */
    public function seeInTitle(string $title): self
    {
        $this->tester->seeInField(['css' => self::FORM . ' input[name="heading"]'], $title);
        return $this;
    }

    public function fillBody(string $body): self
    {
        $this->tester->fillCkEditorById('body', $body);
        return $this;
    }

    public function selectTemplate(): self
    {
        $this->tester->selectOption(self::FORM . ' select[name="page_template"]', self::TEMPLATE_MY_LEARNING);
        return $this;
    }

    public function checkGroupPermission(): self
    {
        $inputs = $this->tester->grabMultiple('#folderForm > section > fieldset:nth-child(2) input', 'id');
        foreach ($inputs as $input) {
            if (strpos($input, 'group-permission') === false) {
                continue;
            }
            $this->tester->checkOption('#' . $input);
        }
        return $this;
    }

    /**
     * @return $this
     */
    public function submit(): int
    {
        $i = $this->tester;
        $i->click(self::SELECTOR_BUTTON_POST);
        $currentUrl = $i->grabFromCurrentUrl();
        $postId = substr($currentUrl, strrpos($currentUrl, '/') + 1);
        $i->comment($postId);
        return intval($postId);
    }
}
