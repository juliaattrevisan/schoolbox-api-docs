<?php

namespace Folder;

use AcceptanceTester;
use Exception;
use Codeception\Util\Locator;
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

        $createFolder->typeName($folderName);
        $createFolder->setHomepageTemplate($homepageTemplateOption);
        $createFolder->setAccessPermission('groupWrite1');
        $createFolder->setAccessPermission('groupWrite2');
        //senior student write permission
        $createFolder->setAccessPermission('groupWrite3');
        $createFolder->setAccessPermission('groupWrite4');
        //junior student read permission
        $createFolder->setAccessPermission('groupRead5');
        //middle student none permission
        $createFolder->setAccessPermission('groupNone6');
        $createFolder->clickCreateGroupToggle();
        $createFolder->clickCreateFolderButton();

        $manageGroup->waitManageGroupTitleToBePresented();
        $manageGroup->clickTab('settings');
        $manageGroup->setAccessPermission('type-3');
        $manageGroup->clickSubmitButton();

        //getting logged in as a junior student to check the group is presented to them
        $i->impersonate($studentJunior);
        $i->amOnUrl('https://vagrant.dev.schoolbox.cloud/groups#groups-i-can-join');
        $i->canSee('Group Free');

        //getting logged in as a middle student to check the group is NOT presented to them
        $i->impersonateViaModifyUserPage($studentMiddle);
        $i->amOnUrl('https://vagrant.dev.schoolbox.cloud/groups#groups-i-can-join');
        $i->cantSee('Group Free');

        //getting logged in as a senior student to check the group is presented to them
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

        $createFolder->typeName($folderName);
        $createFolder->setHomepageTemplate($homepageTemplateOption);
        $createFolder->setAccessPermission('groupWrite1');
        $createFolder->setAccessPermission('groupWrite2');
        //senior student write permission
        $createFolder->setAccessPermission('groupWrite3');
        $createFolder->setAccessPermission('groupWrite4');
        //junior student read permission
        $createFolder->setAccessPermission('groupRead5');
        //middle student none permission
        $createFolder->setAccessPermission('groupNone6');
        $createFolder->clickCreateGroupToggle();
        $createFolder->clickCreateFolderButton();

        $manageGroup->waitManageGroupTitleToBePresented();
        $manageGroup->clickTab('settings');
        $manageGroup->setAccessPermission('type-2');
        $manageGroup->clickSubmitButton();

        //getting logged in as a junior student to check the group is presented to them
        $i->impersonate($studentJunior);
        $i->amOnUrl('https://vagrant.dev.schoolbox.cloud/groups#groups-i-can-join');
        $i->canSee('Group Moderated');

        //getting logged in as a middle student to check the group is NOT presented to them
        $i->impersonateViaModifyUserPage($studentMiddle);
        $i->amOnUrl('https://vagrant.dev.schoolbox.cloud/groups#groups-i-can-join');
        $i->cantSee('Group Moderated');

        //getting logged in as a senior student to check the group is presented to them
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

        $createFolder->typeName($folderName);
        $createFolder->setHomepageTemplate($homepageTemplateOption);
        $createFolder->setAccessPermission('groupWrite1');
        $createFolder->setAccessPermission('groupWrite2');
        //senior student write permission
        $createFolder->setAccessPermission('groupWrite3');
        $createFolder->setAccessPermission('groupWrite4');
        //junior student read permission
        $createFolder->setAccessPermission('groupRead5');
        //middle student none permission
        $createFolder->setAccessPermission('groupNone6');
        $createFolder->clickCreateGroupToggle();
        $createFolder->clickCreateFolderButton();

        $manageGroup->waitManageGroupTitleToBePresented();
        $manageGroup->clickTab('settings');
        $manageGroup->setAccessPermission('type-1');
        $manageGroup->clickSubmitButton();

        //getting logged in as a junior student to check the group is NOT presented to them
        $i->impersonate($studentJunior);
        $i->amOnUrl('https://vagrant.dev.schoolbox.cloud/groups#groups-i-can-join');
        $i->cantSee('Group Restricted');

        //getting logged in as a middle student to check the group is NOT presented to them
        $i->impersonateViaModifyUserPage($studentMiddle);
        $i->amOnUrl('https://vagrant.dev.schoolbox.cloud/groups#groups-i-can-join');
        $i->cantSee('Group Restricted');

        //getting logged in as a senior student to check the group is NOT presented to them
        $i->impersonateViaModifyUserPage($studentSenior);
        $i->amOnUrl('https://vagrant.dev.schoolbox.cloud/groups#groups-i-can-join');
        $i->cantSee('Group Restricted');
    }
}
