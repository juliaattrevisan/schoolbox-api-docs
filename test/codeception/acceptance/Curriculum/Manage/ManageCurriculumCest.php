<?php

declare(strict_types=1);

namespace ManageCurriculum;

use AcceptanceTester;
use Codeception\Util\Locator;
use Page\Curriculum\Manage\ManageCurriculum;
use Page\Curriculum\Manage\action\AddNewCurriculum;
use Page\Curriculum\Manage\action\AddNewSubsection;
use Page\Curriculum\Manage\action\DisableNodes;
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
        $mc = new ManageCurriculum($i);
        $i->amOnPage($mc::URL);
    }

    /**
     * @param AcceptanceTester $i
     * @throws Exception
     *
     * on https://vagrant.dev.schoolbox.cloud/curriculum create a new curriculum
     */
    // public function addNewCurriculum(AcceptanceTester $i)
    // {
    //     $addNew = new AddNewCurriculum($i);
    //     $i->amGoingTo('add a new curriculum');
    //     $addNew->clickDisplay();
    //     $i->amGoingTo('ensure the add button is disabled until I add a name');
    //     $addNew->seeAddButtonIsDisabled();
    //     $addNew->fillName('Party Town');
    //     $addNew->fillCode('PT');
    //     $addNew->clickAddButton();
    //     $i->amGoingTo('ensure the toast message is shown');
    //     $i->canSee('Curriculum successfully added');
    //     $i->amGoingTo('ensure I can see the new curriculum');
    //     $i->canSee('[PT] Party Town');
    // }

    /**
     * @param AcceptanceTester $i
     * @throws Exception
     *
     * on https://vagrant.dev.schoolbox.cloud/curriculum create a new subsection
     */
    // public function addNewSubsection(AcceptanceTester $i)
    // {
    //     $addNew = new AddNewSubsection($i);
    //     $i->amGoingTo('add a new subsection to English');
    //     $addNew->clickDisplay('English');
    //     $i->amGoingTo('ensure the add button is disabled until I add a name');
    //     $addNew->seeAddButtonIsDisabled();
    //     $addNew->fillName('Grammar');
    //     $addNew->fillCode('GR');
    //     $addNew->clickAddButton();
    //     $i->amGoingTo('ensure the toast message is shown');
    //     $i->canSee('Subsection successfully added');
    //     $i->amGoingTo('ensure I can see the new subsection');
    //     $i->canSee('[GR] Grammar');
    // }

    /**
     * @param AcceptanceTester $i
     * @throws Exception
     *
     * on https://vagrant.dev.schoolbox.cloud/curriculum disable some nodes
     */
    public function disableNodes(AcceptanceTester $i)
    {
        $disableNodes = new DisableNodes($i);
        $i->amGoingTo('disable Year 4 English and Year 9 History');
        $disableNodes->checkNode('#checkbox-247');
        $disableNodes->checkNode('#checkbox-1');
        $disableNodes->clickDisableButton();
        $disableNodes->waitForConfirmModal();
        $disableNodes->clickConfirmButton();
        $i->amGoingTo('ensure the toast message is shown');
        $i->canSee('Node successfully disabled');
    }

}