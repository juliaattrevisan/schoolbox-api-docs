<?php

namespace Tests\Support\Page\Group;

use Tests\Support\AcceptanceTester;
use Codeception\Util\Locator;

class ManageGroup
{
    public const URL = '/group/modify/{group_id}';
    public const URL_GROUP_ID = '{group_id}';

    public const SUBMIT_BUTTON = 'input[type=submit][value=Submit]';
    public const UPDATE_BUTTON = 'button.button.submit';
    public const ELEMENT_SEARCH = '#search-text';

    /**
     * This test's acceptance tester actor.
     */
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
     * wait Manage Group Title to be presented
     */
    public function waitManageGroupTitleToBePresented(): void
    {
        $this->tester->waitForText('Manage Group');
    }

    /**
     * set access permission
     * free: type-3
     * moderated: type-2
     * restricted: type-1
     */
    public function setAccessPermission($accessPermission): void
    {
        $this->tester->click(['css' => 'label[for=' . $accessPermission . '][class=radio-label]']);
    }

    /**
     * click submit button
     */
    public function clickSubmitButton(): void
    {
        $this->tester->click(self::SUBMIT_BUTTON);
    }

    /**
     * click modal update button
     */
    public function clickUpdateButton(): void
    {
        $this->tester->click(Locator::lastElement(self::UPDATE_BUTTON));
    }

    /**
     * click an option on the radio button From: name, group, role, list
     * @param $option
     * @return void
     */
    public function clickFromRadioButton($option): void
    {
        $this->tester->click('label[for=' . $option . ']');
    }

    /**
     * search user, group, role by typing a text in the search bar
     * @param string $text
     * @return $this
     */
    public function search(string $text): self
    {
        $this->tester->fillField(self::ELEMENT_SEARCH, $text);
        return $this;
    }

    /**
     * select the user in the dropdown by passing user id
     * @param int $userId
     * @return void
     */
    public function selectUser(int $userId): void
    {
        $this->tester->waitForElementVisible('label[for=checkbox-' . $userId . ']');
        $this->tester->click('label[for=checkbox-' . $userId . ']');
    }
}
