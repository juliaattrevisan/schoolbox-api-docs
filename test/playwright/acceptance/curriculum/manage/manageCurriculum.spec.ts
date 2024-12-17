/**
 * This test creates a curriculum, adds a subsection, disable, updates and delete nodes, search nodes (with disabled nodes toggle on and off),
 * check the nodes sorting, navigates through the nodes by clicking the nodes and using the browser buttons (back and forward)
 * 1. Provision sales.tar.gz data
 * 2. Execute npx playwright test manageCurriculum.spec.ts
 */

import { test, expect } from "@schoolboxTest/playwright/support/integrations";
import ManageCurriculum from "@schoolboxTest/playwright/support/page/curriculum/manage/ManageCurriculum";
import AddNewCurriculum from "@schoolboxTest/playwright/support/page/curriculum/manage/action/AddNewCurriculum";
import AddNewSubsection from "@schoolboxTest/playwright/support/page/curriculum/manage/action/AddNewSubsection";
import DisableNodes from "@schoolboxTest/playwright/support/page/curriculum/manage/action/DisableNodes";

test.describe("Manage Curriculum Tests", () => {
    let mc: ManageCurriculum;

    test.beforeEach(async ({ tester, page }) => {
        await tester.loginAsAdmin();

        mc = new ManageCurriculum(page, tester);
        await mc.goto();
    });

    test("Add New Curriculum", async ({ tester, page }) => {
        tester.isGoingTo("add a new curriculum");
        const addNewCurriculum = new AddNewCurriculum(page, tester);
        await addNewCurriculum.clickDisplay();

        tester.isGoingTo("ensure the add button is disabled until I add a name");
        await addNewCurriculum.seeAddButtonIsDisabled();
        await addNewCurriculum.fillName("Party Town");
        await addNewCurriculum.fillCode("PT");
        await addNewCurriculum.clickAddButton();

        tester.isGoingTo("ensure the toast message is shown");
        await expect(page.getByTestId("toast")).toContainText("Curriculum successfully added");

        tester.isGoingTo("ensure I can see the new curriculum");
        await addNewCurriculum.seeCurriculumExists("[PT] Party Town");
    });

    test("Add New Subsection", async ({ tester, page }) => {
        tester.isGoingTo("add a new subsection to English");
        const addNewSubsection = new AddNewSubsection(page, tester);
        await addNewSubsection.clickDisplay("English");

        tester.isGoingTo("ensure the add button is disabled until I add a name");
        await addNewSubsection.seeAddButtonIsDisabled();
        await addNewSubsection.fillName("Grammar");
        await addNewSubsection.fillCode("GR");
        await addNewSubsection.clickAddButton();

        tester.isGoingTo("ensure the toast message is shown");
        await expect(page.getByTestId("toast")).toContainText("Subsection successfully added");

        tester.isGoingTo("ensure I can see the new subsection");
        await addNewSubsection.seeSubsectionExists("[GR] Grammar");
    });

    test("Disable Nodes", async ({ tester, page }) => {
        tester.isGoingTo("disable Year 4 History and Year 9 History");
        const manageCurriculum = new ManageCurriculum(page, tester);
        const disableNodes = new DisableNodes(page, tester);

        // Check Year 4 and Year 9 History nodes
        await manageCurriculum.checkNode("Year 4 History");
        await manageCurriculum.checkNode("Year 9 History");

        // Click Disable button
        await disableNodes.clickDisableButton();

        tester.isGoingTo("ensure the confirmation modal appears");
        await disableNodes.waitForConfirmModal();

        tester.isGoingTo("confirm the disable action");
        await disableNodes.clickConfirmButton();

        tester.isGoingTo("ensure the toast message is shown");
        await expect(page.getByTestId("toast")).toContainText("Outcomes successfully disabled");
    });

    test.afterEach(async ({ tester }) => {
        await tester.logout();
    });

    /**
     * Soft deleting data to not impact the tests
     */
    test.afterAll(async ({ baseTester, db }) => {
        baseTester.isGoingTo(
            "update delete_at for the data created in this test so it does impact next suite of tests",
        );
        await db.updateInDatabase(
            "curriculum_node",
            { deleted_at: "2022-01-15 11:22:34" },
            { "name like": "Party Town" },
        );
        await db.updateInDatabase("curriculum_node", { deleted_at: "2022-01-15 11:22:34" }, { "name like": "Grammar" });

        await db.updateInDatabase("curriculum_node", { "`order`": 0 }, { "name like": "Year 4 History" });

        await db.updateInDatabase(
            "curriculum_node",
            { name: "Year 9 History" },
            { "name like": "Year 9 Ancient History" },
        );

        await db.updateInDatabase("curriculum_node", { code: "" }, { "name like": "Year 9 History" });

        await db.updateInDatabase("curriculum_node", { description: "" }, { "name like": "Year 9 History" });

        await db.updateInDatabase("curriculum_node", { deleted_at: null }, { "name like": "Year 12 French" });

        await db.updateInDatabase("curriculum_node", { enabled: 1 }, { "name like": "Year 4 History" });

        await db.updateInDatabase("curriculum_node", { enabled: 1 }, { "name like": "Year 9 History" });
    });
});

// <?php

// /**
//  * @copyright   Copyright 2022, Schoolbox Pty Ltd
//  * @link        https://schoolbox.com.au/
//  */

// declare(strict_types=1);

// namespace Tests\Acceptance\Curriculum\Manage;

// use Tests\Support\AcceptanceTester;
// use Tests\Support\Page\Curriculum\Manage\ManageCurriculum;
// use Tests\Support\Page\Curriculum\Manage\Action\AddNewCurriculum;
// use Tests\Support\Page\Curriculum\Manage\Action\AddNewSubsection;
// use Tests\Support\Page\Curriculum\Manage\Action\DisableNodes;
// use Tests\Support\Page\Curriculum\Manage\Action\DeleteNodes;
// use Tests\Support\Page\Curriculum\Manage\Action\SearchNodes;
// use Tests\Support\Page\Curriculum\Manage\Action\UpdateNode;
// use Tests\Support\Page\Curriculum\Manage\Action\NavigateWithBrowserButtons;
// use Exception;

// /**
//  * This test creates a curriculum, adds a subsection, disable, updates and delete nodes, search nodes (with disabled nodes toggle on and off),
//  * check the nodes sorting, navigates through the nodes by clicking the nodes and using the browser buttons (back and forward)
//  * 1. Provision sales.tar.gz data
//  * 2. vagrant ssh
//  * 3. Execute cd /usr/share/schoolbox && ./vendor/bin/codecept run --steps Acceptance Curriculum/Manage/ManageCurriculumCest
//  */

// class ManageCurriculumCest
// {
//     /**
//      * Preparations
//      *
//      * @param AcceptanceTester $i
//      * @throws Exception
//      *
//      * get logged in
//      * navigate to https://vagrant.dev.schoolbox.cloud/curriculum
//      */
//     public function _before(AcceptanceTester $i): void
//     {
//         $i->logout();
//         $i->loginAsAdmin();
//         $i->amOnPage(ManageCurriculum::URL);
//     }

//     /**
//      * @param AcceptanceTester $i
//      * @throws Exception
//      *
//      * on https://vagrant.dev.schoolbox.cloud/curriculum create a new curriculum
//      */
//     public function addNewCurriculum(AcceptanceTester $i): void
//     {
//         $i->amGoingTo('add a new curriculum');
//         $addNew = new AddNewCurriculum($i);
//         $addNew->clickDisplay();
//         $i->amGoingTo('ensure the add button is disabled until I add a name');
//         $addNew->seeAddButtonIsDisabled();
//         $addNew->fillName('Party Town');
//         $addNew->fillCode('PT');
//         $addNew->clickAddButton();
//         $i->amGoingTo('ensure the toast message is shown');
//         $i->canSee('Curriculum successfully added');
//         $i->amGoingTo('ensure I can see the new curriculum');
//         $i->canSee('[PT] Party Town');
//     }

//     /**
//      * @param AcceptanceTester $i
//      * @throws Exception
//      *
//      * on https://vagrant.dev.schoolbox.cloud/curriculum create a new subsection
//      */
//     public function addNewSubsection(AcceptanceTester $i): void
//     {
//         $i->amGoingTo('add a new subsection to English');
//         $addNew = new AddNewSubsection($i);
//         $addNew->clickDisplay('English');
//         $i->amGoingTo('ensure the add button is disabled until I add a name');
//         $addNew->seeAddButtonIsDisabled();
//         $addNew->fillName('Grammar');
//         $addNew->fillCode('GR');
//         $addNew->clickAddButton();
//         $i->amGoingTo('ensure the toast message is shown');
//         $i->canSee('Subsection successfully added');
//         $i->amGoingTo('ensure I can see the new subsection');
//         $i->canSee('[GR] Grammar');
//     }

//     /**
//      * @param AcceptanceTester $i
//      * @throws Exception
//      *
//      * on https://vagrant.dev.schoolbox.cloud/curriculum disable some nodes
//      */
//     public function disableNodes(AcceptanceTester $i): void
//     {
//         $i->amGoingTo('disable Year 4 History and Year 9 History');
//         $mc = new ManageCurriculum($i);
//         $mc->checkNode('#checkbox-247');
//         $mc->checkNode('#checkbox-1');
//         $disableNodes = new DisableNodes($i);
//         $disableNodes->clickDisableButton();
//         $disableNodes->waitForConfirmModal();
//         $disableNodes->clickConfirmButton();
//         $i->amGoingTo('ensure the toast message is shown');
//         $mc->waitForToast();
//         $i->canSee('Outcomes successfully disabled');
//     }

//     /**
//      * @param AcceptanceTester $i
//      * @throws Exception
//      *
//      * on https://vagrant.dev.schoolbox.cloud/curriculum delete a node
//      */
//     public function deleteNode(AcceptanceTester $i): void
//     {
//         $i->amGoingTo('delete the Year 12 French Node');
//         $mc = new ManageCurriculum($i);
//         $mc->checkNode('#checkbox-302');
//         $deleteNodes = new DeleteNodes($i);
//         $deleteNodes->clickDeleteButton();
//         $deleteNodes->waitForConfirmModal();
//         $deleteNodes->clickConfirmButton();
//         $i->amGoingTo('ensure the toast message is shown');
//         $mc->waitForToast();
//         $i->canSee('Outcome successfully deleted');
//         $i->dontSee('Year 12 French');
//     }

//     /**
//      * @param AcceptanceTester $i
//      * @throws Exception
//      *
//      * on https://vagrant.dev.schoolbox.cloud/curriculum search for nodes
//      */
//     public function searchNodes(AcceptanceTester $i): void
//     {
//         $i->amGoingTo('search for nodes containing "historical" in Year 4 History');
//         $mc = new ManageCurriculum($i);
//         $mc->clickNode('Year 4 History');
//         $searchNodes = new SearchNodes($i);
//         $searchNodes->fillSearch('historical');
//         $i->amGoingTo('expect 4 search results are shown');
//         $searchNodes->seeResultsMatch(4);
//     }

//     /**
//      * @param AcceptanceTester $i
//      * @throws Exception
//      *
//      * on https://vagrant.dev.schoolbox.cloud/curriculum update a node
//      */
//     public function updateNode(AcceptanceTester $i): void
//     {
//         $i->amGoingTo('update Year 9 History');
//         $updateNode = new UpdateNode($i);
//         $updateNode->clickDisplay('Year 9 History');
//         $updateNode->fillName('Year 9 Ancient History');
//         $updateNode->fillCode('9AH');
//         $updateNode->fillDescription('1', 'Here we will focus mainly on the Romans, Egyptians and Babylonians');
//         $updateNode->clickUpdateButton();
//         $i->amGoingTo('ensure the toast message is shown');
//         $i->canSee('Outcome successfully updated');
//         $i->amGoingTo('ensure the updated node displays correctly');
//         $i->canSee('[9AH] Year 9 Ancient History');
//     }

//     /**
//      * @depends disableNodes
//      * @param AcceptanceTester $i
//      * @throws Exception
//      *
//      * on https://vagrant.dev.schoolbox.cloud/curriculum searches for a node with disabled nodes toggle on and off
//      *
//      */
//     public function hideDisabledNodesAndSearch(AcceptanceTester $i): void
//     {
//         $i->amGoingTo('navigate to First Contacts in Year 4 History');
//         $mc = new ManageCurriculum($i);
//         $mc->clickNode('Year 4 History');
//         $mc->clickNode('Year 4');
//         $mc->clickNode('Historical Knowledge and Understanding');
//         $mc->clickNode('First Contacts');
//         $i->amGoingTo('search for nodes containing ACHH');
//         $searchNodes = new SearchNodes($i);
//         $searchNodes->fillSearch('ACHH');
//         $i->amGoingTo('expect 4 search results are shown');
//         $searchNodes->seeResultsMatch(4);
//         $i->amGoingTo('hide disabled nodes and so expect no search results are now shown');
//         $searchNodes->uncheckShowDisabledNodes();
//         $i->waitForElementNotVisible('[data-test-node]', 1); // waiting for the animation to hide the disabled nodes
//         $searchNodes->seeResultsMatch(0);
//     }

//     /**
//      * @param AcceptanceTester $i
//      * @throws Exception
//      *
//      * on https://vagrant.dev.schoolbox.cloud/curriculum checks the curriculum nodes sorting
//      */
//     public function checkNodeSort(AcceptanceTester $i): void
//     {
//         $i->amGoingTo('check nodes are sorted by order, code, then name');
//         $i->amGoingTo('add order: 1 to Year 4 History so it appears at the top of the list');
//         $i->updateInDatabase('curriculum_node', array('order' => '1'), array('name' => 'Year 4 History'));
//         $i->reloadPage();
//         $expectedOrder = array(
//             'Year 4 History',
//             '[9AH] Year 9 Ancient History',
//             '[PT] Party Town',
//             'English',
//             'Years 9 and 10 French'
//         );
//         $i->amGoingTo('loop through the Curricula children, ensuring the correct order');
//         $nodeList = $i->grabMultiple('[data-test="view-node-name"]');
//         for ($index = 1; $index <= count($nodeList) - 1; $index++) {
//             $i->assertStringContainsString($expectedOrder[$index - 1], $nodeList[$index]);
//         }
//     }

//     /**
//      * @param AcceptanceTester $i
//      * @throws Exception
//      *
//      * on https://vagrant.dev.schoolbox.cloud/curriculum
//      * navigates through the nodes by clicking the nodes and using the browser buttons (back and forward)
//      */
//     public function navigateWithBrowserButtons(AcceptanceTester $i): void
//     {
//         $i->amGoingTo('use the browser backward and forward buttons to traverse the curriculum');
//         $i->amGoingTo('traverse to "Curricula > English > Year 9 > Literature > Creating literature" and ensure "ACELT1773" is visible');
//         $navigate = new NavigateWithBrowserButtons($i);
//         $navigate->clickDisplay('English');
//         $navigate->clickDisplay('Year 9');
//         $navigate->clickDisplay('Literature');
//         $navigate->clickDisplay('Creating literature');
//         $i->canSee('ACELT1773');
//         $i->amGoingTo('press backwards twice to "Curricula > English > Year 9" and ensure "Achievement standard" is visible');
//         $navigate->moveBackwards();
//         $navigate->moveBackwards();
//         $i->canSee('Achievement standard');
//         $i->amGoingTo('press backwards twice again to Curricula and ensure "Years 9 and 10 French" is visible');
//         $navigate->moveBackwards();
//         $navigate->moveBackwards();
//         $i->canSee('Years 9 and 10 French');
//         $i->amGoingTo('press forwards three times to "Curricula > English > Year 9 > Literature" and ensure "Responding to literature" is visible');
//         $navigate->moveForwards();
//         $navigate->moveForwards();
//         $navigate->moveForwards();
//         $i->canSee('Responding to literature');
//         $i->amGoingTo('press back twice to "Curricula > English" then search for "ACELT1638", select it and ensure "ELBE1201" is visible');
//         $navigate->moveBackwards();
//         $navigate->moveBackwards();
//         $searchNodes = new SearchNodes($i);
//         $searchNodes->fillSearch('ACELT1638');
//         $navigate->clickDisplay('[ACELT1638] Experiment with the ways that language features, image and sound can be adapted in literary texts, for example the effects of stereotypical characters and settings, the playfulness of humour and pun and the use of hyperlink');
//         $i->canSee('ELBE1201');
//         $i->amGoingTo('press backwards once to "Curricula > English" and ensure "Year 9" is visible');
//         $navigate->moveBackwards();
//         $i->canSee('Year 9');
//         $i->amGoingTo('press forwards once to "Curricula > English > Year 9 > Literature > Creating literature" and ensure "ELBE1202" is visible');
//         $navigate->moveForwards();
//         $i->canSee('ELBE1202');
//     }

//     /**
//      * Soft deleting data to not impact the tests
//      *
//      * @param AcceptanceTester $i
//      */
//     public function tearDown(AcceptanceTester $i): void
//     {
//         $i->amGoingTo("update delete_at for the data created in this test so it does impact next suite of tests");
//         $i->updateInDatabase('curriculum_node', ['deleted_at' => "2022-01-15 11:22:34"], ['name like' => 'Party Town']);
//         $i->updateInDatabase('curriculum_node', ['deleted_at' => "2022-01-15 11:22:34"], ['name like' => 'Grammar']);
//         $i->updateInDatabase('curriculum_node', array('order' => '0'), array('name' => 'Year 4 History'));
//         $i->updateInDatabase('curriculum_node', ['name' => "Year 9 History"], ['name like' => 'Year 9 Ancient History']);
//         $i->updateInDatabase('curriculum_node', ['code' => ''], ['name like' => 'Year 9 History']);
//         $i->updateInDatabase('curriculum_node', ['description' => ''], ['name like' => 'Year 9 History']);
//         $i->updateInDatabase('curriculum_node', ['deleted_at' => null], ['name like' => 'Year 12 French']);
//         $i->updateInDatabase('curriculum_node', ['enabled' => 1], ['name like' => 'Year 4 History']);
//         $i->updateInDatabase('curriculum_node', ['enabled' => 1], ['name like' => 'Year 9 History']);
//     }
// }
