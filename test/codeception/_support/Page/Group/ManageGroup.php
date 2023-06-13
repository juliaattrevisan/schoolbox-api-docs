<?php

namespace Page\Group;

use AcceptanceTester;
use Codeception\Util\Locator;

class ManageGroup
{
    public const URL = '/group/modify/{group_id}';
    public const URL_GROUP_ID = '{group_id}';

    public const SUBMIT_BUTTON = 'input[type=submit][value=Submit]';
    public const UPDATE_BUTTON = 'button.button.submit';

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
}
