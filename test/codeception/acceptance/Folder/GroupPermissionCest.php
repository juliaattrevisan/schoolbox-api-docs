<?php

namespace Folder;

use AcceptanceTester;
use Exception;
use Page\Group\ManageGroup;
use Page\Folder\CreateFolder;

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
    public function _before(AcceptanceTester $i)
    {
        $i->amGoingTo('Get logged in and navigate to learninng/badge page');
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
    public function groupsAccessibilityGroupFree(AcceptanceTester $i)
    {
        $createFolder = new CreateFolder($i);
        $manageGroup = new ManageGroup($i);
        $folderName = "Group Free";
        $homepageTemplateOption = "Course";

        $studentJunior = 'Eden Reed';
        $studentMiddle = 'Louisa Smith';
        $studentSenior = 'Tim Reed';

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

        $manageGroup->waitManageGroupTitleToBePresented();
        $manageGroup->setAccessPermission('type-3');
        $manageGroup->clickUpdateButton();

        $i->amGoingTo('get logged in as a junior student to check the group is presented to them');
        $i->impersonate($studentJunior);
        $i->amOnUrl('https://vagrant.dev.schoolbox.cloud/groups#groups-i-can-join');
        $i->canSee('Group Free');

        $i->amGoingTo('get logged in as a middle student to check the group is NOT presented to them');
        $i->impersonateViaModifyUserPage($studentMiddle);
        $i->amOnUrl('https://vagrant.dev.schoolbox.cloud/groups#groups-i-can-join');
        $i->cantSee('Group Free');

        $i->amGoingTo('get logged in as a senior student to check the group is presented to them');
        $i->impersonateViaModifyUserPage($studentSenior);
        $i->amOnUrl('https://vagrant.dev.schoolbox.cloud/groups#groups-i-can-join');
        $i->canSee('Group Free');
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
    public function groupsAccessibilityGroupModerated(AcceptanceTester $i)
    {
        $createFolder = new CreateFolder($i);
        $manageGroup = new ManageGroup($i);
        $folderName = "Group Moderated";
        $homepageTemplateOption = "Course";

        $studentJunior = 'Eden Reed';
        $studentMiddle = 'Louisa Smith';
        $studentSenior = 'Tim Reed';

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

        $manageGroup->waitManageGroupTitleToBePresented();
        $manageGroup->setAccessPermission('type-2');
        $manageGroup->clickUpdateButton();

        $i->amGoingTo('get logged in as a junior student to check the group is presented to them');
        $i->impersonate($studentJunior);
        $i->amOnUrl('https://vagrant.dev.schoolbox.cloud/groups#groups-i-can-join');
        $i->canSee('Group Moderated');

        $i->amGoingTo('get  logged in as a middle student to check the group is NOT presented to them');
        $i->impersonateViaModifyUserPage($studentMiddle);
        $i->amOnUrl('https://vagrant.dev.schoolbox.cloud/groups#groups-i-can-join');
        $i->cantSee('Group Moderated');

        $i->amGoingTo('get  logged in as a senior student to check the group is presented to them');
        $i->impersonateViaModifyUserPage($studentSenior);
        $i->amOnUrl('https://vagrant.dev.schoolbox.cloud/groups#groups-i-can-join');
        $i->canSee('Group Moderated');
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
    public function groupsAccessibilityGroupRestricted(AcceptanceTester $i)
    {
        $createFolder = new CreateFolder($i);
        $manageGroup = new ManageGroup($i);
        $folderName = "Group Restricted";
        $homepageTemplateOption = "Course";

        $studentJunior = 'Eden Reed';
        $studentMiddle = 'Louisa Smith';
        $studentSenior = 'Tim Reed';

        $i->amGoingTo('create a group with write permission to teachers');
        $createFolder->typeName($folderName);
        $createFolder->setHomepageTemplate($homepageTemplateOption);
        $createFolder->setAccessPermission('groupWrite1');
        $createFolder->setAccessPermission('groupWrite2');
        $i->amGoingTo('set write permission to senior student');
        $createFolder->setAccessPermission('groupWrite3');
        $createFolder->setAccessPermission('groupWrite4');
        $i->amGoingTo('set read permission to jjunior student');
        $createFolder->setAccessPermission('groupRead5');
        $i->amGoingTo('set none permission to middle student');
        $createFolder->setAccessPermission('groupNone6');
        $createFolder->clickCreateGroupToggle();
        $createFolder->clickCreateFolderButton();

        $manageGroup->waitManageGroupTitleToBePresented();
        $manageGroup->setAccessPermission('type-1');
        $manageGroup->clickUpdateButton();

        $i->amGoingTo('get logged in as a junior student to check the group is NOT presented to them');
        $i->impersonate($studentJunior);
        $i->amOnUrl('https://vagrant.dev.schoolbox.cloud/groups#groups-i-can-join');
        $i->cantSee('Group Restricted');

        $i->amGoingTo('get logged in as a middle student to check the group is NOT presented to them');
        $i->impersonateViaModifyUserPage($studentMiddle);
        $i->amOnUrl('https://vagrant.dev.schoolbox.cloud/groups#groups-i-can-join');
        $i->cantSee('Group Restricted');

        $i->amGoingTo('get logged in as a senior student to check the group is NOT presented to them');
        $i->impersonateViaModifyUserPage($studentSenior);
        $i->amOnUrl('https://vagrant.dev.schoolbox.cloud/groups#groups-i-can-join');
        $i->cantSee('Group Restricted');
    }

    public function inheritPermissions(AcceptanceTester $i): void
    {
        $createFolder           = new CreateFolder($i);
        $manageGroup            = new ManageGroup($i);
        $folderName             = "Group Root";
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

        $i->amGoingTo('Add Jack Coles as group admin');
        $manageGroup->clickFromRadioButton('list');
        $manageGroup->search('jcoles');
        $manageGroup->selectUser(22);
        $manageGroup->clickFromRadioButton('role');
        $i->click('Add Members');



    }
}
