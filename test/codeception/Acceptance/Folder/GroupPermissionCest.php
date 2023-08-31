<?php

namespace Tests\Acceptance\Folder;

use Tests\Support\AcceptanceTester;
use Exception;
use Tests\Support\Page\Common;
use Tests\Support\Page\Group\ManageGroup;
use Tests\Support\Page\Folder\CreateFolder;
use Tests\Support\Page\Resources\Resources;

class GroupPermissionCest
{
    /**
     * Preparations
     *
     * @param AcceptanceTester $i
     * @throws Exception
     *
     * get logged in
     * navigate to https://vagrant.dev.schoolbox.cloud/learning/badge
     */
    public function _before(AcceptanceTester $i): void
    {
        $i->amGoingTo('Get logged in and navigate to learning/badge page');
        $i->logout();
        $i->loginAsAdmin();
        $createFolder = new createFolder($i);
        $i->amOnPage($createFolder::URL);
        $createFolder->waitCreateFolderTitleToBePresented();
    }

    /**
     * @param AcceptanceTester $i
     * @throws Exception
     *
     * create groups with permissions that allow users to join the groups
     * folder: read/group free
     * folder: none/group free
     * folder: write/group free
     * get logged in as that users and check whether that groups are presented to join or not
     * table with scenarios described in 13387
     */
    public function groupsAccessibilityGroupFree(AcceptanceTester $i): void
    {
        $createFolder = new CreateFolder($i);
        $manageGroup = new ManageGroup($i);
        $folderName = "Group Free";
        $homepageTemplateOption = "Course";

        $studentJunior = 'Ms Eden Reed';
        $studentMiddle = 'Louisa Smith';
        $studentSenior = 'Mr Tim Reed';
        $groupMember = 'Ms Eloise White';

        $i->amGoingTo('create a group with write permission to teachers');
        $createFolder->typeName($folderName);
        $createFolder->setHomepageTemplate($homepageTemplateOption);
        $createFolder->setAccessPermission('groupWrite1');
        $createFolder->setAccessPermission('groupWrite2');
        $i->amGoingTo('set write permission to senior student');
        $createFolder->setAccessPermission('groupWrite3');
        $createFolder->setAccessPermission('groupWrite4');
        $i->amGoingTo('set write permission to junior student');
        $createFolder->setAccessPermission('groupRead5');
        $i->amGoingTo('set none permission to middle student');
        $createFolder->setAccessPermission('groupNone6');
        $createFolder->clickCreateGroupToggle();
        $createFolder->clickCreateFolderButton();
        $folderId = $i->grabFirstFolderByTitle($folderName)->getId();

        $manageGroup->waitManageGroupTitleToBePresented();
        $manageGroup->setAccessPermission('type-3');
        $manageGroup->clickUpdateButton();
        $i->amGoingTo('add  Eloise White  into the group');
        $manageGroup->search('elwhite');
        $manageGroup->selectUser(50);
        $manageGroup->clickFromRadioButton('role');
        $i->click('Add Members');
        sleep(1);

        $i->amGoingTo('get logged in as a junior student to check the group is presented to them');
        $i->impersonate($studentJunior);
        $i->amOnUrl('https://vagrant.dev.schoolbox.cloud/groups#groups-i-can-join');
        sleep(1);
        $i->canSee('Group Free');

        $i->amGoingTo('get logged in as a middle student to check the group is NOT presented to them');
        $i->impersonateViaModifyUserPage($studentMiddle);
        $i->amOnUrl('https://vagrant.dev.schoolbox.cloud/groups#groups-i-can-join');
        sleep(1);
        $i->cantSee('Group Free');

        $i->amGoingTo('get logged in as a senior student to check the group is presented to them');
        $i->impersonateViaModifyUserPage($studentSenior);
        $i->amOnUrl('https://vagrant.dev.schoolbox.cloud/groups#groups-i-can-join');
        sleep(1);
        $i->canSee('Group Free');

        $i->amGoingTo('get logged in as Eloise White and check she is able to leave the group');
        $i->impersonateViaModifyUserPage($groupMember);
        $i->amOnUrl('https://vagrant.dev.schoolbox.cloud/homepage/' . $folderId);
        $i->clickElementInWebComponent(Common::SELECTOR_WC_ACTIONS_DROPDOWN, Common::SELECTOR_WC_ACTIONS_DROPDOWN_BUTTON);
        $i->canSee('Leave Group');
        $i->click('Leave Group');
        $i->clickElementInWebComponent(Common::SELECTOR_WC_ACTIONS_DROPDOWN, Common::SELECTOR_WC_ACTIONS_DROPDOWN_BUTTON);
        $i->canSee('Join Group');
    }

    /**
     * @param AcceptanceTester $i
     * @throws Exception
     *
     * create groups with permissions that allow users to join the groups
     * folder: read/group moderated
     * folder: none/group moderated
     * folder: write/group moderated
     * get logged in as that users and check whether that groups are presented to join or not
     * table with scenarios described in 13387
     */
    public function groupsAccessibilityGroupModerated(AcceptanceTester $i): void
    {
        $createFolder = new CreateFolder($i);
        $manageGroup = new ManageGroup($i);
        $folderName = "Group Moderated";
        $homepageTemplateOption = "Course";

        $studentJunior = 'Ms Eden Reed';
        $studentMiddle = 'Louisa Smith';
        $studentSenior = 'Mr Tim Reed';
        $groupMember = 'Ms Eloise White';

        $i->amGoingTo('create a group with write permission to teachers');
        $createFolder->typeName($folderName);
        $createFolder->setHomepageTemplate($homepageTemplateOption);
        $createFolder->setAccessPermission('groupWrite1');
        $createFolder->setAccessPermission('groupWrite2');
        $i->amGoingTo('set write permission to senior student');
        $createFolder->setAccessPermission('groupWrite3');
        $createFolder->setAccessPermission('groupWrite4');
        $i->amGoingTo('set read permission to junior student');
        $createFolder->setAccessPermission('groupRead5');
        $i->amGoingTo('set none permission to middle student');
        $createFolder->setAccessPermission('groupNone6');
        $createFolder->clickCreateGroupToggle();
        $createFolder->clickCreateFolderButton();
        $folderId = $i->grabFirstFolderByTitle($folderName)->getId();

        $manageGroup->waitManageGroupTitleToBePresented();
        $manageGroup->setAccessPermission('type-2');
        $manageGroup->clickUpdateButton();
        $i->amGoingTo('add Jana Reed into the group');
        $manageGroup->search('elwhite');
        $manageGroup->selectUser(50);
        $manageGroup->clickFromRadioButton('role');
        $i->click('Add Members');
        sleep(1);

        $i->amGoingTo('get logged in as a junior student to check the group is presented to them');
        $i->impersonate($studentJunior);
        $i->amOnUrl('https://vagrant.dev.schoolbox.cloud/groups#groups-i-can-join');
        sleep(1);
        $i->canSee('Group Moderated');

        $i->amGoingTo('get  logged in as a middle student to check the group is NOT presented to them');
        $i->impersonateViaModifyUserPage($studentMiddle);
        $i->amOnUrl('https://vagrant.dev.schoolbox.cloud/groups#groups-i-can-join');
        sleep(1);
        $i->cantSee('Group Moderated');

        $i->amGoingTo('get  logged in as a senior student to check the group is presented to them');
        $i->impersonateViaModifyUserPage($studentSenior);
        $i->amOnUrl('https://vagrant.dev.schoolbox.cloud/groups#groups-i-can-join');
        sleep(1);
        $i->canSee('Group Moderated');

        $i->amGoingTo('get logged in as Eloise White and check she is able to leave the group');
        $i->impersonateViaModifyUserPage($groupMember);
        $i->amOnUrl('https://vagrant.dev.schoolbox.cloud/homepage/' . $folderId);
        $i->clickElementInWebComponent(Common::SELECTOR_WC_ACTIONS_DROPDOWN, Common::SELECTOR_WC_ACTIONS_DROPDOWN_BUTTON);
        sleep(1);
        $i->canSee('Leave Group');
        $i->click('Leave Group');
        $i->clickElementInWebComponent(Common::SELECTOR_WC_ACTIONS_DROPDOWN, Common::SELECTOR_WC_ACTIONS_DROPDOWN_BUTTON);
        sleep(1);
        $i->cantSee('Cancel Application');
    }

    /**
     * @param AcceptanceTester $i
     * @throws Exception
     *
     * create groups with permissions that allow users to join the groups
     * folder: read/group moderated
     * folder: none/group moderated
     * folder: write/group moderated
     * get logged in as that users and check whether that groups are presented to join or not
     * table with scenarios described in 13387
     */
    public function groupsAccessibilityGroupRestricted(AcceptanceTester $i): void
    {
        $createFolder = new CreateFolder($i);
        $manageGroup = new ManageGroup($i);
        $folderName = "Group Restricted";
        $homepageTemplateOption = "Course";

        $studentJunior = 'Ms Eden Reed';
        $studentMiddle = 'Louisa Smith';
        $studentSenior = 'Mr Tim Reed';
        $groupMember = 'Ms Jana Reed';

        $i->amGoingTo('create a group with write permission to teachers');
        $createFolder->typeName($folderName);
        $createFolder->setHomepageTemplate($homepageTemplateOption);
        $createFolder->setAccessPermission('groupWrite1');
        $createFolder->setAccessPermission('groupWrite2');
        $i->amGoingTo('set write permission to senior student');
        $createFolder->setAccessPermission('groupWrite3');
        $createFolder->setAccessPermission('groupWrite4');
        $i->amGoingTo('set read permission to junior student');
        $createFolder->setAccessPermission('groupRead5');
        $i->amGoingTo('set none permission to middle student');
        $createFolder->setAccessPermission('groupNone6');
        $createFolder->clickCreateGroupToggle();
        $createFolder->clickCreateFolderButton();
        $folderId = $i->grabFirstFolderByTitle($folderName)->getId();

        $manageGroup->waitManageGroupTitleToBePresented();
        $manageGroup->setAccessPermission('type-1');
        $manageGroup->clickUpdateButton();
        $i->amGoingTo('add Jana Reed into the group');
        $manageGroup->search('jreed');
        $manageGroup->selectUser(25);
        $manageGroup->clickFromRadioButton('role');
        $i->click('Add Members');
        sleep(1);

        $i->amGoingTo('get logged in as a junior student to check the group is NOT presented to them');
        $i->impersonateViaModifyUserPage($studentJunior);
        $i->amOnUrl('https://vagrant.dev.schoolbox.cloud/groups#groups-i-can-join');
        sleep(1);
        $i->cantSee('Group Restricted');

        $i->amGoingTo('get logged in as a middle student to check the group is NOT presented to them');
        $i->impersonateViaModifyUserPage($studentMiddle);
        $i->amOnUrl('https://vagrant.dev.schoolbox.cloud/groups#groups-i-can-join');
        sleep(1);
        $i->cantSee('Group Restricted');

        $i->amGoingTo('get logged in as a senior student to check the group is NOT presented to them');
        $i->impersonateViaModifyUserPage($studentSenior);
        $i->amOnUrl('https://vagrant.dev.schoolbox.cloud/groups#groups-i-can-join');
        sleep(1);
        $i->cantSee('Group Restricted');

        $i->amGoingTo('get logged in as Jana Reed and check she is unable to leave the group');
        $i->impersonateViaModifyUserPage($groupMember);
        $i->amOnUrl('https://vagrant.dev.schoolbox.cloud/homepage/' . $folderId);
        $i->clickElementInWebComponent(Common::SELECTOR_WC_ACTIONS_DROPDOWN, Common::SELECTOR_WC_ACTIONS_DROPDOWN_BUTTON);
        $i->cantSee('Leave Group');
    }

    /**
     * @param AcceptanceTester $i
     * @return void
     * @throws Exception
     *
     * create a group with Jack Coles as admin
     * create a sub folder to that group
     * access that sub folder as Jack Coles
     * access that sub folder as Jana Reed to see access denied
     */
    public function inheritPermissions(AcceptanceTester $i): void
    {
        $createFolder           = new CreateFolder($i);
        $manageGroup            = new ManageGroup($i);
        $resources              = new Resources($i);
        $folderName             = "Group Root";
        $subFolderName          = "Group 1";
        $homepageTemplateOption = "Course";

        $i->amGoingTo('create a group with none permission to teachers');
        $createFolder->typeName($folderName);
        $createFolder->setHomepageTemplate($homepageTemplateOption);
        $createFolder->setAccessPermission('groupWrite1');
        $createFolder->setAccessPermission('groupNone2');
        $i->amGoingTo('set write permission to senior student');
        $createFolder->setAccessPermission('groupNone3');
        $createFolder->setAccessPermission('groupNone4');
        $i->amGoingTo('set read permission to junior student');
        $createFolder->setAccessPermission('groupNone5');
        $i->amGoingTo('set none permission to middle student');
        $createFolder->setAccessPermission('groupNone6');
        $createFolder->clickCreateGroupToggle();
        $createFolder->clickCreateFolderButton();

        $manageGroup->waitManageGroupTitleToBePresented();
        $manageGroup->setAccessPermission('type-1');
        $manageGroup->clickUpdateButton();

        $i->amGoingTo('Add Jack Coles into the group');
        $manageGroup->search('jcoles');
        $manageGroup->selectUser(22);
        $manageGroup->clickFromRadioButton('role');
        $i->click('Add Members');
        $i->amGoingTo('Making Jack Coles the group admin via database');
        $folderId = $i->grabFromDatabase('folder', 'id', ['deleted_at' => null, 'name like' => $folderName]);
        $i->updateInDatabase('userAccess', ['type' => '3'], ['sid' => $folderId]);

        $i->amGoingTo('Create a sub folder under the group');
        $i->amOnPage($createFolder::URL_RESOURCES);
        $resources->clickFolderName($folderName);
        $i->waitForText("Homepage", 5);
        $resources->clickMenuItem('folder');
        $createFolder->typeName($subFolderName);
        $createFolder->setHomepageTemplate($homepageTemplateOption);
        $createFolder->clickCreateFolderButton();

        $i->amGoingTo('Impersonate Jack Coles and access the sub folder');
        $i->impersonate('Jack Coles');
        $i->amOnPage($createFolder::URL_RESOURCES);
        $resources->clickFolderName($folderName);
        $resources->clickFolderName($subFolderName);
        $resources->clickMenuItem('home');
        $i->canSee('Group 1');
        $i->cantSee('You do not have permission to view this page.');

        $i->amGoingTo('Impersonate Jana Reed and access the sub folder');
        $i->impersonate('Jana Reed');
        $folderId = $i->grabFromDatabase('folder', 'id', ['deleted_at' => null, 'name like' => $subFolderName]);
        $i->amOnPage('/homepage/' . $folderId);
        $i->cantSee('Group 1');
        $i->canSee('You do not have permission to view this page.');
    }
}
