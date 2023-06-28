<?php

declare(strict_types=1);

namespace Tests\Acceptance\Curriculum\Manage;

use Tests\Support\AcceptanceTester;
use Tests\Support\Page\Curriculum\Manage\ManageCurriculum;
use Tests\Support\Page\Curriculum\Manage\Action\AddNewCurriculum;
use Tests\Support\Page\Curriculum\Manage\Action\AddNewSubsection;
use Tests\Support\Page\Curriculum\Manage\Action\DisableNodes;
use Tests\Support\Page\Curriculum\Manage\Action\DeleteNodes;
use Tests\Support\Page\Curriculum\Manage\Action\SearchNodes;
use Tests\Support\Page\Curriculum\Manage\Action\UpdateNode;
use Tests\Support\Page\Curriculum\Manage\Action\NavigateWithBrowserButtons;
use Exception;

class ManageCurriculumCest
{
    /**
     * Preparations
     *
     * @param AcceptanceTester $i
     * @throws Exception
     *
     * get logged in
     * navigate to https://vagrant.dev.schoolbox.cloud/curriculum
     */
    public function _before(AcceptanceTester $i)
    {
        $i->logout();
        $i->loginAsAdmin();
        $i->amOnPage(ManageCurriculum::URL);
    }

    /**
     * @param AcceptanceTester $i
     * @throws Exception
     *
     * on https://vagrant.dev.schoolbox.cloud/curriculum create a new curriculum
     */
    public function addNewCurriculum(AcceptanceTester $i)
    {
        $i->amGoingTo('add a new curriculum');
        $addNew = new AddNewCurriculum($i);
        $addNew->clickDisplay();
        $i->amGoingTo('ensure the add button is disabled until I add a name');
        $addNew->seeAddButtonIsDisabled();
        $addNew->fillName('Party Town');
        $addNew->fillCode('PT');
        $addNew->clickAddButton();
        $i->amGoingTo('ensure the toast message is shown');
        $i->canSee('Curriculum successfully added');
        $i->amGoingTo('ensure I can see the new curriculum');
        $i->canSee('[PT] Party Town');
    }

    /**
     * @param AcceptanceTester $i
     * @throws Exception
     *
     * on https://vagrant.dev.schoolbox.cloud/curriculum create a new subsection
     */
    public function addNewSubsection(AcceptanceTester $i)
    {
        $i->amGoingTo('add a new subsection to English');
        $addNew = new AddNewSubsection($i);
        $addNew->clickDisplay('English');
        $i->amGoingTo('ensure the add button is disabled until I add a name');
        $addNew->seeAddButtonIsDisabled();
        $addNew->fillName('Grammar');
        $addNew->fillCode('GR');
        $addNew->clickAddButton();
        $i->amGoingTo('ensure the toast message is shown');
        $i->canSee('Subsection successfully added');
        $i->amGoingTo('ensure I can see the new subsection');
        $i->canSee('[GR] Grammar');
    }

    /**
     * @param AcceptanceTester $i
     * @throws Exception
     *
     * on https://vagrant.dev.schoolbox.cloud/curriculum disable some nodes
     */
    public function disableNodes(AcceptanceTester $i)
    {
        $i->amGoingTo('disable Year 4 History and Year 9 History');
        $mc = new ManageCurriculum($i);
        $mc->checkNode('#checkbox-247');
        $mc->checkNode('#checkbox-1');
        $disableNodes = new DisableNodes($i);
        $disableNodes->clickDisableButton();
        $disableNodes->waitForConfirmModal();
        $disableNodes->clickConfirmButton();
        $i->amGoingTo('ensure the toast message is shown');
        $mc->waitForToast();
        $i->canSee('Nodes successfully disabled');
    }

    /**
     * @param AcceptanceTester $i
     * @throws Exception
     *
     * on https://vagrant.dev.schoolbox.cloud/curriculum delete a node
     */
    public function deleteNode(AcceptanceTester $i)
    {
        $i->amGoingTo('delete the Year 12 French Node');
        $mc = new ManageCurriculum($i);
        $mc->checkNode('#checkbox-302');
        $deleteNodes = new DeleteNodes($i);
        $deleteNodes->clickDeleteButton();
        $deleteNodes->waitForConfirmModal();
        $deleteNodes->clickConfirmButton();
        $i->amGoingTo('ensure the toast message is shown');
        $mc->waitForToast();
        $i->canSee('Node successfully deleted');
        $i->dontSee('Year 12 French');
    }

    /**
     * @param AcceptanceTester $i
     * @throws Exception
     *
     * on https://vagrant.dev.schoolbox.cloud/curriculum search for nodes
     */
    public function searchNodes(AcceptanceTester $i)
    {
        $i->amGoingTo('search for nodes containing "historical" in Year 4 History');
        $mc = new ManageCurriculum($i);
        $mc->clickNode('Year 4 History');
        $searchNodes = new SearchNodes($i);
        $searchNodes->fillSearch('historical');
        $i->amGoingTo('expect 4 search results are shown');
        $searchNodes->seeResultsMatch(4);
    }

    /**
     * @param AcceptanceTester $i
     * @throws Exception
     *
     * on https://vagrant.dev.schoolbox.cloud/curriculum update a node
     */
    public function updateNode(AcceptanceTester $i)
    {
        $i->amGoingTo('update Year 9 History');
        $updateNode = new UpdateNode($i);
        $updateNode->clickDisplay('Year 9 History');
        $updateNode->fillName('Year 9 Ancient History');
        $updateNode->fillCode('9AH');
        $updateNode->fillDescription('1', 'Here we will focus mainly on the Romans, Egyptians and Babylonians');
        $updateNode->clickUpdateButton();
        $i->amGoingTo('ensure the toast message is shown');
        $i->canSee('Node successfully updated');
        $i->amGoingTo('ensure the updated node displays correctly');
        $i->canSee('[9AH] Year 9 Ancient History');
    }

    /**
     * @depends disableNodes
     * @param AcceptanceTester $i
     * @throws Exception
     *
     * on https://vagrant.dev.schoolbox.cloud/curriculum
     *
     */
    public function hideDisabledNodesAndSearch(AcceptanceTester $i)
    {
        $i->amGoingTo('navigate to First Contacts in Year 4 History');
        $mc = new ManageCurriculum($i);
        $mc->clickNode('Year 4 History');
        $mc->clickNode('Year 4');
        $mc->clickNode('Historical Knowledge and Understanding');
        $mc->clickNode('First Contacts');
        $i->amGoingTo('search for nodes containing ACHH');
        $searchNodes = new SearchNodes($i);
        $searchNodes->fillSearch('ACHH');
        $i->amGoingTo('expect 4 search results are shown');
        $searchNodes->seeResultsMatch(4);
        $i->amGoingTo('hide disabled nodes and so expect no search results are now shown');
        $searchNodes->uncheckShowDisabledNodes();
        $i->waitForElementNotVisible('[data-test-node]', 1); // waiting for the animation to hide the disabled nodes
        $searchNodes->seeResultsMatch(0);
    }

    /**
     * @param AcceptanceTester $i
     * @throws Exception
     *
     * on https://vagrant.dev.schoolbox.cloud/curriculum
     */
    public function checkNodeSort(AcceptanceTester $i)
    {
        $i->amGoingTo('check nodes are sorted by order, code, then name');
        $i->amGoingTo('add order: 1 to Year 4 History so it appears at the top of the list');
        $i->updateInDatabase('curriculum_node', array('order' => '1'), array('name' => 'Year 4 History'));
        $i->reloadPage();
        $expectedOrder = array(
            'Year 4 History',
            '[9AH] Year 9 Ancient History',
            '[PT] Party Town',
            'English',
            'Years 9 and 10 French'
        );
        $i->amGoingTo('loop through the Curricula children, ensuring the correct order');
        $nodeList = $i->grabMultiple('[data-test-view-node-name]');
        for ($index = 1; $index <= count($nodeList) - 1; $index++) {
            $i->assertStringContainsString($expectedOrder[$index - 1], $nodeList[$index]);
        }
    }

    /**
     * @param AcceptanceTester $i
     * @throws Exception
     *
     * on https://vagrant.dev.schoolbox.cloud/curriculum update a node
     */
    public function navigateWithBrowserButtons(AcceptanceTester $i)
    {
        $i->amGoingTo('use the browser backward and forward buttons to traverse the curriculum');
        $i->amGoingTo('traverse to "Curricula > English > Year 9 > Literature > Creating literature" and ensure "ACELT1773" is visible');
        $navigate = new NavigateWithBrowserButtons($i);
        $navigate->clickDisplay('English');
        $navigate->clickDisplay('Year 9');
        $navigate->clickDisplay('Literature');
        $navigate->clickDisplay('Creating literature');
        $i->canSee('ACELT1773');
        $i->amGoingTo('press backwards twice to "Curricula > English > Year 9" and ensure "Achievement standard" is visible');
        $navigate->moveBackwards();
        $navigate->moveBackwards();
        $i->canSee('Achievement standard');
        $i->amGoingTo('press backwards twice again to Curricula and ensure "Years 9 and 10 French" is visible');
        $navigate->moveBackwards();
        $navigate->moveBackwards();
        $i->canSee('Years 9 and 10 French');
        $i->amGoingTo('press forwards three times to "Curricula > English > Year 9 > Literature" and ensure "Responding to literature" is visible');
        $navigate->moveForwards();
        $navigate->moveForwards();
        $navigate->moveForwards();
        $i->canSee('Responding to literature');
        $i->amGoingTo('press back twice to "Curricula > English" then search for "ACELT1638", select it and ensure "ELBE1201" is visible');
        $navigate->moveBackwards();
        $navigate->moveBackwards();
        $searchNodes = new SearchNodes($i);
        $searchNodes->fillSearch('ACELT1638');
        $navigate->clickDisplay('[ACELT1638] Experiment with the ways that language features, image and sound can be adapted in literary texts, for example the effects of stereotypical characters and settings, the playfulness of humour and pun and the use of hyperlink');
        $i->canSee('ELBE1201');
        $i->amGoingTo('press backwards once to "Curricula > English" and ensure "Year 9" is visible');
        $navigate->moveBackwards();
        $i->canSee('Year 9');
        $i->amGoingTo('press forwards once to "Curricula > English > Year 9 > Literature > Creating literature" and ensure "ELBE1202" is visible');
        $navigate->moveForwards();
        $i->canSee('ELBE1202');
    }
}
