<?php

namespace Page\Badges;

use AcceptanceTester;

class CreateBadge
{
    public const BADGE_NAME = '#badge-name';
    public const BROWSE_ICON_BUTTON =  'a[data-reveal-id="icon-modal"]';
    public const MODAL = 'div#icon-modal';
    public const CATEGORY_DROPDOWN = '#badge-category';
    public const CREATE_BADGE_BUTTON = '#badge-submit';
    private AcceptanceTester $tester;

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
     * click Create Badge button
     */
    public function typeName($badgeName): void
    {
        $this->tester->fillField(self::BADGE_NAME, $badgeName);
    }

    /**
     * click Browse icon library button
     */
    public function clickBrowseIconLibraryButton(): void
    {
        $this->tester->click(self::BROWSE_ICON_BUTTON);
    }

    /**
     * wait appearance of the modal dialog
     * @throws \Exception
     */
    public function waitModalDialogPresented(): void
    {
        $this->tester->waitForElement(self::MODAL);
    }

    /**
     * click a vertical Tab
     */
    public function clickVerticalTabItem(string $title): void
    {
        $this->tester->click('li[data-icon-path=' . $title . ']');
    }

    /**
     * click one of the badges images
     */
    public function clickBadgeImage(string $image): void
    {
        $this->tester->click('img[src$="/images/badges/' . $image . '.svg"]');
    }

    /**
     * click category
     */
    public function clickCategoryDropDown(): void
    {
        $this->tester->click(self::CATEGORY_DROPDOWN);
    }

    /**
     * click one of the categories
     */
    public function clickCategoryItem(string $category): void
    {
        $this->tester->click('option[label="' . $category . '"]');
    }

    /**
     * click Create Badge button
     */
    public function clickCreateBadgeButton(): void
    {
        $this->tester->click(self::CREATE_BADGE_BUTTON);
    }
}
