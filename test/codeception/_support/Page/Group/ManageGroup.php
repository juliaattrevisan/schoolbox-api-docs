<?php

namespace Page\Group;

use AcceptanceTester;

class ManageGroup
{
    public const URL = '/group/modify/{group_id}';
    public const URL_GROUP_ID = '{group_id}';

    public const SUBMIT_BUTTON = 'input[type=submit][value=Submit]';


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
     * click one of the Tabs: current members (members), waiting list (waitingList), add members (addMembers), settings (settings)
     */
    public function clickTab($tabName): void
    {
        $this->tester->click('a[href="#' . $tabName . '"]');
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
}
