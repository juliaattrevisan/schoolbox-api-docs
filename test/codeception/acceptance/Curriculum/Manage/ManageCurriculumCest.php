<?php

declare(strict_types=1);

namespace Curriculum\Manage;

use AcceptanceTester;
use Page\Curriculum\Manage\ManageCurriculum;
use Page\Curriculum\Manage\Action\AddNewCurriculum;
use Page\Curriculum\Manage\Action\AddNewSubsection;
use Page\Curriculum\Manage\Action\DisableNodes;
use Page\Curriculum\Manage\Action\DeleteNodes;
use Page\Curriculum\Manage\Action\SearchNodes;
use Page\Curriculum\Manage\Action\UpdateNode;
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
}
